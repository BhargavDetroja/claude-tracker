<?php

namespace App\Http\Controllers;

use App\Services\PixelMascot;
use App\Services\UsagePoller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DropdownController extends Controller
{
    public function __construct(
        private readonly UsagePoller $poller,
        private readonly PixelMascot $mascot,
    ) {}

    /**
     * The panel that drops down from the menu bar. Rendered with whatever the
     * last poll produced, then kept current over IPC rather than reloading.
     */
    public function __invoke(): View
    {
        return view('dropdown', [
            'usage' => $this->poller->current(),
            'cells' => $this->mascot->cells(),
            'grid' => PixelMascot::GRID,
            'bounds' => $this->mascot->bodyBounds(),
        ]);
    }

    /**
     * The last stored reading, with no outbound request.
     *
     * The panel polls this while it is open so it stays current even if an
     * IPC broadcast is missed. It only reads the cache, so calling it often
     * costs nothing and never touches Anthropic.
     */
    public function current(): JsonResponse
    {
        return response()->json($this->poller->current());
    }

    /**
     * The panel's own refresh button. The resulting UsageUpdated event repaints
     * every open surface, so the response body is only for the caller's
     * error handling.
     */
    public function refresh(): JsonResponse
    {
        return response()->json($this->poller->poll());
    }
}
