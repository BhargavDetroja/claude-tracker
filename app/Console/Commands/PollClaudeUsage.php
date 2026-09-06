<?php

namespace App\Console\Commands;

use App\Services\UsagePoller;
use Illuminate\Console\Command;

class PollClaudeUsage extends Command
{
    protected $signature = 'claude:poll-usage {--if-stale= : Only poll when the last reading is older than this many seconds}';

    protected $description = "Read Claude Code's usage limits and update the menu bar";

    public function handle(UsagePoller $poller): int
    {
        $maximumAge = $this->option('if-stale');

        $snapshot = $maximumAge === null
            ? $poller->poll()
            : $poller->pollIfStale((int) $maximumAge);

        if ($snapshot->error !== null) {
            $this->components->warn($snapshot->error);
        }

        $this->components->twoColumnDetail('Session (5 hour)', $this->format($snapshot->fiveHourPercent));
        $this->components->twoColumnDetail('Weekly (7 day)', $this->format($snapshot->sevenDayPercent));

        return self::SUCCESS;
    }

    private function format(?float $percent): string
    {
        return $percent === null ? 'unknown' : round($percent).'%';
    }
}
