<?php

use App\Services\UsageSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Which mascot rows are lit, keyed by row. Reads the SVG groups only, since
 * the stylesheet also contains the literal string data-lit="true".
 *
 * @return array<int, bool>
 */
function rowStates(string $html): array
{
    preg_match_all('/data-row="(\d+)"\s+data-lit="(true|false)"/', $html, $matches, PREG_SET_ORDER);

    $rows = [];

    foreach ($matches as $match) {
        $rows[(int) $match[1]] = $match[2] === 'true';
    }

    return $rows;
}

function rememberUsage(float $fiveHour, float $sevenDay): void
{
    Cache::forever(
        config('claude.cache_key'),
        UsageSnapshot::fromApiResponse(claudeUsagePayload($fiveHour, $sevenDay))->toArray(),
    );
}

it('renders the panel from the last stored reading', function () {
    rememberUsage(42.0, 13.0);

    $this->get(route('dropdown'))
        ->assertOk()
        ->assertSee('42%')
        ->assertSee('13%');
});

it('draws the mascot with his eyes', function () {
    rememberUsage(42.0, 13.0);

    $this->get(route('dropdown'))
        ->assertOk()
        ->assertSee('class="eye"', escape: false)
        ->assertSee('class="mascot"', escape: false);
});

it('fills the mascot from his feet upward', function () {
    rememberUsage(50.0, 13.0);

    $rows = rowStates($this->get(route('dropdown'))->assertOk()->getContent());

    $lit = array_keys(array_filter($rows));
    $cold = array_keys(array_filter($rows, fn (bool $isLit): bool => ! $isLit));

    /** Rows are numbered downward, so every lit row must sit below every cold one. */
    expect($lit)->not->toBeEmpty()
        ->and($cold)->not->toBeEmpty()
        ->and(min($lit))->toBeGreaterThan(max($cold));
});

it('leaves him empty at nothing used and solid once spent', function () {
    rememberUsage(0.0, 0.0);
    expect(array_filter(rowStates($this->get(route('dropdown'))->getContent())))->toBeEmpty();

    rememberUsage(100.0, 40.0);
    expect(array_filter(
        rowStates($this->get(route('dropdown'))->getContent()),
        fn (bool $isLit): bool => ! $isLit,
    ))->toBeEmpty();
});

it('still renders before the first reading arrives', function () {
    $this->get(route('dropdown'))
        ->assertOk()
        ->assertSee('Waiting for the first reading.');
});

it('shows the failure reason when a reading went stale', function () {
    Cache::forever(
        config('claude.cache_key'),
        UsageSnapshot::fromApiResponse(claudeUsagePayload(42.0, 13.0))
            ->markStale('Couldn\'t reach the usage endpoint.')
            ->toArray(),
    );

    $this->get(route('dropdown'))
        ->assertOk()
        ->assertSee('42%')
        ->assertSee('Stale')
        ->assertSee('Couldn\'t reach the usage endpoint.');
});

it('serves the last stored reading without calling Anthropic', function () {
    rememberUsage(42.0, 13.0);
    Http::fake();

    $this->getJson(route('dropdown.usage'))
        ->assertOk()
        ->assertJson(['five_hour_percent' => 42.0, 'seven_day_percent' => 13.0, 'is_stale' => false]);

    /** The panel polls this every fifteen seconds, so it must stay local. */
    Http::assertNothingSent();
});

it('re-polls on demand from the panel', function () {
    Process::fake(['*find-generic-password*' => Process::result(output: claudeKeychainBlob())]);
    Http::fake(['api.anthropic.com/*' => Http::response(claudeUsagePayload(77.0, 21.0))]);

    $this->postJson(route('dropdown.refresh'))
        ->assertOk()
        ->assertJson([
            'five_hour_percent' => 77.0,
            'seven_day_percent' => 21.0,
            'is_stale' => false,
        ]);
});
