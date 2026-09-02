<?php

namespace App\Events;

use App\Services\UsageSnapshot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to the dropdown window every time a poll completes.
 *
 * NativePHP's EventWatcher picks up anything broadcast on the "nativephp"
 * channel and forwards it over IPC, so the panel listens with
 * `window.Native.on('App\\Events\\UsageUpdated', ...)` rather than polling
 * the DOM or re-requesting the page.
 */
class UsageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The snapshot as a plain array, because only public properties survive
     * the JSON encode on the way to the renderer.
     *
     * @var array<string, mixed>
     */
    public array $usage;

    public function __construct(UsageSnapshot $snapshot)
    {
        $this->usage = $snapshot->toArray();
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('nativephp')];
    }
}
