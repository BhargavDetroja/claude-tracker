<?php

use App\Events\UsageUpdated;
use App\Services\UsagePoller;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

const KEYCHAIN_COMMAND = '*find-generic-password*';

function fakeKeychain(?string $blob = null): void
{
    Process::fake([
        KEYCHAIN_COMMAND => Process::result(output: $blob ?? claudeKeychainBlob()),
    ]);
}

it('stores a reading and announces it to the panel', function () {
    Event::fake([UsageUpdated::class]);
    fakeKeychain();
    Http::fake(['api.anthropic.com/*' => Http::response(claudeUsagePayload(42.0, 13.0))]);

    $snapshot = app(UsagePoller::class)->poll();

    expect($snapshot->fiveHourPercent)->toBe(42.0)
        ->and($snapshot->isStale)->toBeFalse()
        ->and(Cache::get(config('claude.cache_key'))['five_hour_percent'])->toBe(42.0);

    Event::assertDispatched(UsageUpdated::class, function (UsageUpdated $event): bool {
        return $event->usage['five_hour_percent'] === 42.0;
    });
});

it('sends the oauth bearer token and beta header', function () {
    fakeKeychain();
    Http::fake(['api.anthropic.com/*' => Http::response(claudeUsagePayload())]);

    app(UsagePoller::class)->poll();

    Http::assertSent(function ($request): bool {
        return $request->url() === config('claude.usage_endpoint')
            && $request->hasHeader('Authorization', 'Bearer sk-ant-oat01-testing')
            && $request->hasHeader('anthropic-beta', config('claude.oauth_beta'));
    });
});

it('keeps the last good reading when the endpoint fails', function () {
    fakeKeychain();
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(claudeUsagePayload(42.0, 13.0), 200)
            ->push('gateway down', 503),
    ]);

    app(UsagePoller::class)->poll();
    $snapshot = app(UsagePoller::class)->poll();

    expect($snapshot->fiveHourPercent)->toBe(42.0)
        ->and($snapshot->isStale)->toBeTrue()
        ->and($snapshot->error)->toContain('503');
});

it('degrades to an empty reading when it has never succeeded', function () {
    fakeKeychain();
    Http::fake(['api.anthropic.com/*' => Http::response('nope', 500)]);

    $snapshot = app(UsagePoller::class)->poll();

    expect($snapshot->fiveHourPercent)->toBeNull()
        ->and($snapshot->isStale)->toBeTrue()
        ->and($snapshot->menuBarLabel())->toBe('—');
});

it('explains an expired token rather than reporting a bare failure', function () {
    fakeKeychain(claudeKeychainBlob(expiresAt: (time() - 60) * 1000));
    Http::fake();

    $snapshot = app(UsagePoller::class)->poll();

    expect($snapshot->error)->toContain('expired');
    Http::assertNothingSent();
});

it('explains a revoked token when the endpoint rejects it', function () {
    fakeKeychain();
    Http::fake(['api.anthropic.com/*' => Http::response('', 401)]);

    expect(app(UsagePoller::class)->poll()->error)->toContain('sign in again');
});

it('points the user at Claude Code when the keychain entry is missing', function () {
    Process::fake([
        KEYCHAIN_COMMAND => Process::result(errorOutput: 'The specified item could not be found.', exitCode: 44),
    ]);
    Http::fake();

    expect(app(UsagePoller::class)->poll()->error)->toContain('Sign in to Claude Code');
    Http::assertNothingSent();
});

it('treats an unrecognisable response as a failure rather than zero usage', function () {
    fakeKeychain();
    Http::fake(['api.anthropic.com/*' => Http::response(['something_entirely_new' => true])]);

    $snapshot = app(UsagePoller::class)->poll();

    expect($snapshot->fiveHourPercent)->toBeNull()
        ->and($snapshot->error)->toContain('does not recognise');
});

it('reads the keychain with the service name Claude Code uses', function () {
    fakeKeychain();
    Http::fake(['api.anthropic.com/*' => Http::response(claudeUsagePayload())]);

    app(UsagePoller::class)->poll();

    Process::assertRan(function (PendingProcess $process): bool {
        return in_array(config('claude.keychain_service'), (array) $process->command, strict: true);
    });
});

it('runs from the console and reports both windows', function () {
    fakeKeychain();
    Http::fake(['api.anthropic.com/*' => Http::response(claudeUsagePayload(42.0, 13.0))]);

    $this->artisan('claude:poll-usage')
        ->expectsOutputToContain('42%')
        ->expectsOutputToContain('13%')
        ->assertSuccessful();
});
