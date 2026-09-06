<?php

namespace App\Console\Commands;

use App\Services\UsagePoller;
use Illuminate\Console\Command;
use Throwable;

/**
 * Polls Claude Code's usage on a loop, as a supervised background process.
 *
 * The Laravel scheduler alone is not dependable here. NativePHP drives it from
 * a setInterval in Electron's main process, which macOS App Nap is free to
 * throttle for an app with no visible window, and which NativePHP deliberately
 * stops on sleep and only restarts if the resume event arrives cleanly.
 *
 * A child process has neither problem: it is not the UI process, so App Nap
 * does not target it, a sleeping machine simply pauses it, and NativePHP
 * restarts it if it ever dies. The scheduler still runs alongside as a second
 * line of defence, and {@see UsagePoller::pollIfStale()} keeps the two from
 * both calling Anthropic in the same minute.
 */
class WatchClaudeUsage extends Command
{
    protected $signature = 'claude:watch {--interval= : Seconds between checks}';

    protected $description = "Watch Claude Code's usage limits in the background";

    /**
     * Exit and let the supervisor start a fresh process from time to time, so
     * a watcher left running for weeks never becomes the oldest thing in the
     * app. NativePHP restarts a persistent child process when it ends.
     */
    private const MAX_LIFETIME_SECONDS = 21600;

    public function handle(UsagePoller $poller): int
    {
        $interval = max(15, (int) ($this->option('interval') ?: config('claude.poll_interval')));
        $staleAfter = (int) config('claude.stale_after');
        $startedAt = time();

        $this->components->info("Watching Claude usage every {$interval}s.");

        while (true) {
            try {
                $poller->pollIfStale($staleAfter);
            } catch (Throwable $e) {
                /**
                 * A failed reading is already handled inside the poller, so
                 * anything reaching here is unexpected. Report it and keep
                 * looping rather than dying and thrashing the supervisor.
                 */
                report($e);
            }

            if ((time() - $startedAt) >= self::MAX_LIFETIME_SECONDS) {
                return self::SUCCESS;
            }

            sleep($interval);
        }
    }
}
