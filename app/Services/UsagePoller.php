<?php

namespace App\Services;

use App\Events\UsageUpdated;
use App\Exceptions\UsageUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\MenuBar;

/**
 * Runs one poll cycle: read usage, remember it, repaint the menu bar, and
 * tell the dropdown window about it.
 *
 * A failed poll never blanks the display. The last good reading is kept and
 * re-shown marked stale, which matters because the endpoint behind all of
 * this is undocumented and may simply stop answering one day.
 */
class UsagePoller
{
    public function __construct(
        private readonly ClaudeUsageClient $client,
        private readonly MenuBarIconRenderer $icons,
    ) {}

    public function poll(): UsageSnapshot
    {
        try {
            $snapshot = $this->client->fetch();
        } catch (UsageUnavailable $e) {
            Log::warning('Claude usage poll failed.', ['reason' => $e->getMessage()]);

            $snapshot = $this->lastKnown()?->markStale($e->getMessage())
                ?? UsageSnapshot::unavailable($e->getMessage());
        }

        $this->remember($snapshot);
        $this->paintMenuBar($snapshot);

        UsageUpdated::dispatch($snapshot);

        return $snapshot;
    }

    /**
     * Poll only if the last reading has aged past the given number of seconds.
     *
     * Both the watcher and the scheduler call this, so either one stalling
     * leaves the other still updating, while a healthy pair does not double
     * the requests to Anthropic.
     */
    public function pollIfStale(int $maximumAge): UsageSnapshot
    {
        $last = $this->lastKnown();

        $isFresh = $last !== null
            && ! $last->isStale
            && $last->fetchedAt->diffInSeconds(CarbonImmutable::now()) < $maximumAge;

        return $isFresh ? $last : $this->poll();
    }

    /**
     * The most recent reading, or a placeholder if we have never had one.
     * Used to paint the menu bar and render the panel before the first poll.
     */
    public function current(): UsageSnapshot
    {
        return $this->lastKnown() ?? UsageSnapshot::unavailable('Waiting for the first reading.');
    }

    public function lastKnown(): ?UsageSnapshot
    {
        $cached = Cache::get(config('claude.cache_key'));

        return is_array($cached) ? UsageSnapshot::fromArray($cached) : null;
    }

    public function paintMenuBar(UsageSnapshot $snapshot): void
    {
        /**
         * The MenuBar facade posts to the Electron process, which only exists
         * when the desktop app is the one running us. Guarding here keeps
         * `php artisan claude:poll-usage` usable from a plain terminal.
         */
        if (! config('nativephp-internal.running')) {
            return;
        }

        MenuBar::icon($this->icons->render($snapshot->headlinePercent()));
        MenuBar::label($snapshot->menuBarLabel());
        MenuBar::tooltip($snapshot->tooltip());
    }

    private function remember(UsageSnapshot $snapshot): void
    {
        Cache::forever(config('claude.cache_key'), $snapshot->toArray());
    }
}
