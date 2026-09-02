<?php

use App\Services\UsageSnapshot;

it('reads both windows from the endpoint payload', function () {
    $snapshot = UsageSnapshot::fromApiResponse(claudeUsagePayload(42.0, 13.0));

    expect($snapshot->fiveHourPercent)->toBe(42.0)
        ->and($snapshot->sevenDayPercent)->toBe(13.0)
        ->and($snapshot->fiveHourResetsAt)->not->toBeNull()
        ->and($snapshot->sevenDayResetsAt)->not->toBeNull()
        ->and($snapshot->isStale)->toBeFalse();
});

it('falls back to the limits array when the named windows disappear', function () {
    $payload = claudeUsagePayload(42.0, 13.0);
    unset($payload['five_hour'], $payload['seven_day']);

    $snapshot = UsageSnapshot::fromApiResponse($payload);

    expect($snapshot->fiveHourPercent)->toBe(42.0)
        ->and($snapshot->sevenDayPercent)->toBe(13.0)
        ->and($snapshot->fiveHourResetsAt)->not->toBeNull();
});

it('reports null rather than zero when a window is missing entirely', function () {
    $snapshot = UsageSnapshot::fromApiResponse(['member_dashboard_available' => false]);

    expect($snapshot->fiveHourPercent)->toBeNull()
        ->and($snapshot->sevenDayPercent)->toBeNull()
        ->and($snapshot->menuBarLabel())->toBe('—');
});

it('ignores unparseable values instead of trusting them', function () {
    $snapshot = UsageSnapshot::fromApiResponse([
        'five_hour' => ['utilization' => 'plenty', 'resets_at' => 'soon'],
        'seven_day' => ['utilization' => null],
    ]);

    expect($snapshot->fiveHourPercent)->toBeNull()
        ->and($snapshot->fiveHourResetsAt)->toBeNull()
        ->and($snapshot->sevenDayPercent)->toBeNull();
});

it('clamps utilization into the zero to one hundred range', function () {
    $over = UsageSnapshot::fromApiResponse(['five_hour' => ['utilization' => 143.2]]);
    $under = UsageSnapshot::fromApiResponse(['five_hour' => ['utilization' => -8]]);

    expect($over->fiveHourPercent)->toBe(100.0)
        ->and($under->fiveHourPercent)->toBe(0.0);
});

it('keeps the numbers when a reading goes stale', function () {
    $snapshot = UsageSnapshot::fromApiResponse(claudeUsagePayload(42.0, 13.0))
        ->markStale('Endpoint unreachable.');

    expect($snapshot->fiveHourPercent)->toBe(42.0)
        ->and($snapshot->isStale)->toBeTrue()
        ->and($snapshot->error)->toBe('Endpoint unreachable.')
        ->and($snapshot->menuBarLabel())->toBe('42%');
});

it('chooses the headline percentage from configuration', function () {
    $snapshot = UsageSnapshot::fromApiResponse(claudeUsagePayload(42.0, 88.0));

    config(['claude.menu_bar_metric' => 'five_hour']);
    expect($snapshot->headlinePercent())->toBe(42.0);

    config(['claude.menu_bar_metric' => 'seven_day']);
    expect($snapshot->headlinePercent())->toBe(88.0);

    config(['claude.menu_bar_metric' => 'highest']);
    expect($snapshot->headlinePercent())->toBe(88.0);
});

it('survives a round trip through the cache', function () {
    $original = UsageSnapshot::fromApiResponse(claudeUsagePayload(42.0, 13.0));

    $restored = UsageSnapshot::fromArray($original->toArray());

    expect($restored->fiveHourPercent)->toBe($original->fiveHourPercent)
        ->and($restored->sevenDayPercent)->toBe($original->sevenDayPercent)
        ->and($restored->fiveHourResetsAt?->toIso8601String())
        ->toBe($original->fiveHourResetsAt?->toIso8601String());
});
