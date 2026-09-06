<?php

use App\Console\Commands\PollClaudeUsage;
use Illuminate\Support\Facades\Schedule;

/**
 * The second line of defence.
 *
 * The primary poller is the supervised `claude:watch` child process started in
 * NativeAppServiceProvider. This scheduled run covers the case where that
 * process is not running, and backs off when the watcher has already produced
 * a fresh reading, so the two never both call Anthropic in the same minute.
 *
 * NativePHP drives `schedule:run` from a timer in Electron's main process,
 * which macOS can throttle and which stops on sleep. That is exactly why it is
 * the fallback here rather than the thing the app depends on.
 */
Schedule::command(PollClaudeUsage::class, ['--if-stale' => config('claude.stale_after')])
    ->everyMinute()
    ->withoutOverlapping();
