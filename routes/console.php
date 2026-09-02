<?php

use App\Console\Commands\PollClaudeUsage;
use Illuminate\Support\Facades\Schedule;

/**
 * NativePHP runs `schedule:run` once a minute for us, so this is all the
 * background polling the app needs. Switch to everyThirtySeconds() for a
 * tighter refresh: Laravel keeps the scheduler process alive for the whole
 * minute to service sub-minute tasks, which costs one long-lived PHP process.
 */
Schedule::command(PollClaudeUsage::class)
    ->everyMinute()
    ->withoutOverlapping();
