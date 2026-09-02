<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    /**
     * Generated menu bar icons go to a throwaway directory. Without this a
     * test run would delete the icon the running desktop app is pointing at.
     */
    ->beforeEach(function () {
        config(['claude.icon.path' => sys_get_temp_dir().'/claude-tracker-icons-'.getmypid()]);
    })
    ->afterEach(function () {
        File::deleteDirectory(config('claude.icon.path'));
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A trimmed copy of a real response from the undocumented usage endpoint,
 * including the null-valued internal buckets it ships alongside the two
 * windows this app cares about.
 *
 * @return array<string, mixed>
 */
function claudeUsagePayload(float $fiveHour = 42.0, float $sevenDay = 13.0): array
{
    return [
        'five_hour' => [
            'utilization' => $fiveHour,
            'resets_at' => now()->addHours(3)->toIso8601String(),
            'limit_dollars' => null,
            'locked_reason' => null,
        ],
        'seven_day' => [
            'utilization' => $sevenDay,
            'resets_at' => now()->addDays(5)->toIso8601String(),
        ],
        'seven_day_opus' => null,
        'nimbus_quill' => ['utilization' => 0.0, 'resets_at' => null],
        'limits' => [
            ['kind' => 'session', 'percent' => (int) round($fiveHour), 'resets_at' => now()->addHours(3)->toIso8601String()],
            ['kind' => 'weekly_all', 'percent' => (int) round($sevenDay), 'resets_at' => now()->addDays(5)->toIso8601String()],
        ],
        'member_dashboard_available' => false,
    ];
}

/**
 * The JSON blob Claude Code keeps as the Keychain item's password.
 */
function claudeKeychainBlob(?int $expiresAt = null): string
{
    return json_encode([
        'claudeAiOauth' => [
            'accessToken' => 'sk-ant-oat01-testing',
            'refreshToken' => 'sk-ant-ort01-testing',
            'expiresAt' => $expiresAt ?? (time() + 3600) * 1000,
            'subscriptionType' => 'pro',
        ],
        'organizationUuid' => 'org-testing',
    ]);
}
