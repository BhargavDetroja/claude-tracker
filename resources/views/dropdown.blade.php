<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Claude Tracker</title>

    @php
        /**
         * The mascot is pixel art on a fixed cell grid, the same grid the menu
         * bar icon is drawn from. Cells are grouped by row so the fill can
         * climb from his feet to his head one row at a time.
         */
        [$bodyTop, $bodyBottom] = $bounds;

        $byRow = [];

        foreach ($cells as $cell) {
            $byRow[$cell['y']][] = $cell;
        }

        ksort($byRow);

        $rowIsLit = function (?float $percent, int $row) use ($bodyTop, $bodyBottom): bool {
            if ($percent === null || $percent <= 0) {
                return false;
            }

            $height = $bodyBottom - $bodyTop + 1;
            $lit = (int) round((max(0.0, min(100.0, $percent)) / 100) * $height);

            return $row > $bodyBottom - $lit;
        };

        $severity = match (true) {
            ($usage->fiveHourPercent ?? 0) >= 90 => 'critical',
            ($usage->fiveHourPercent ?? 0) >= 75 => 'high',
            ($usage->fiveHourPercent ?? 0) >= 50 => 'warn',
            default => 'ok',
        };
    @endphp

    <style>
        :root {
            --bg: #F0EEE6;
            --ink: #1A1A18;
            --muted: #6F6B62;
            --hairline: rgba(0, 0, 0, .10);
            --track: rgba(0, 0, 0, .08);

            /* The mascot: spent rows, unspent rows, and his eyes */
            --clay: #C96442;
            --cold: #D8D4C8;
            --eye: #17170F;

            --accent: #6F6B62;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #1C1C1A;
                --ink: #F2F0EA;
                --muted: #918C82;
                --hairline: rgba(255, 255, 255, .12);
                --track: rgba(255, 255, 255, .10);
                --cold: #3A3A36;
                --eye: #0F0F0D;
                --accent: #918C82;
            }
        }

        [data-severity="warn"] { --accent: #C96442; }
        [data-severity="high"] { --accent: #B04A2E; }
        [data-severity="critical"] { --accent: #A32E1E; }

        * { box-sizing: border-box; }

        html, body { margin: 0; height: 100%; overflow: hidden; }

        body {
            background: var(--bg);
            color: var(--ink);
            font: 13px/1.45 -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", sans-serif;
            -webkit-font-smoothing: antialiased;
            user-select: none;
            cursor: default;
        }

        .panel { display: flex; flex-direction: column; height: 100%; padding: 14px 16px 12px; }

        header { display: flex; align-items: center; justify-content: space-between; }
        .title { font-size: 13px; font-weight: 600; letter-spacing: -.01em; }

        .status { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; color: var(--muted); }
        /* A square, not a dot: everything in this panel is on the pixel grid. */
        .status .box { width: 6px; height: 6px; background: var(--accent); }
        .status[data-stale="true"] .box { background: var(--muted); }

        /* ----------------------------------------------------- mascot --- */

        .stage { display: grid; place-items: center; padding: 8px 0 2px; }

        .mascot { width: 164px; height: 164px; shape-rendering: crispEdges; }

        /* Rows light from his feet upward, each one a beat behind the last. */
        .row .body {
            fill: var(--cold);
            transition: fill 220ms steps(2, end);
            transition-delay: var(--d, 0ms);
        }
        .row[data-lit="true"] .body { fill: var(--clay); }

        /* The eyes never change, so he stays himself at every level. */
        .eye { fill: var(--eye); }

        .mascot[data-alarm="true"] { animation: blink 1.1s steps(1, end) infinite; }
        @keyframes blink { 0%, 60% { opacity: 1; } 61%, 100% { opacity: .62; } }

        @media (prefers-reduced-motion: reduce) {
            .row .body { transition: none; }
            .mascot[data-alarm="true"] { animation: none; }
        }

        /* --------------------------------------------------- readouts --- */

        .headline { text-align: center; margin-top: 4px; }
        .headline .value {
            font-size: 34px; font-weight: 650; letter-spacing: -.03em; line-height: 1.05;
            font-variant-numeric: tabular-nums; color: var(--accent);
        }
        .headline .caption { font-size: 11px; color: var(--muted); margin-top: 2px; }

        .weekly { margin-top: 12px; padding-top: 11px; border-top: 1px solid var(--hairline); }
        .weekly-row { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 7px; }
        .weekly-row .label { font-size: 11px; font-weight: 550; }
        .weekly-row .value { font-size: 11px; color: var(--muted); font-variant-numeric: tabular-nums; }

        /* A segmented bar, so it belongs to the same pixel language as the mascot. */
        .bar { display: flex; gap: 2px; }
        .bar i { flex: 1; height: 6px; background: var(--track); transition: background-color 220ms steps(2, end); }
        .bar i[data-on="true"] { background: var(--accent); }

        footer {
            margin-top: auto; padding-top: 10px;
            display: flex; align-items: center; justify-content: space-between;
            font-size: 11px; color: var(--muted);
        }

        .refresh {
            font: inherit; color: var(--ink); background: transparent;
            border: 1px solid var(--hairline); border-radius: 0;
            padding: 4px 10px; cursor: pointer;
            transition: background-color 160ms steps(2, end);
        }
        .refresh:hover { background: var(--track); }
        .refresh:active { transform: translateY(1px); }
        .refresh:disabled { opacity: .5; cursor: default; }

        .notice {
            display: none; margin-top: 8px; padding: 7px 9px;
            background: var(--track); color: var(--muted);
            font-size: 11px; line-height: 1.35;
        }
        .notice[data-visible="true"] { display: block; }
    </style>
</head>
<body>
    <div class="panel" id="panel" data-severity="{{ $severity }}">
        <header>
            <span class="title">Claude Code</span>
            <span class="status" id="status" data-stale="{{ $usage->isStale ? 'true' : 'false' }}">
                <span class="box"></span>
                <span id="status-text">{{ $usage->isStale ? 'Stale' : 'Live' }}</span>
            </span>
        </header>

        <div class="stage">
            <svg class="mascot" id="mascot" viewBox="0 0 {{ $grid }} {{ $grid }}" role="img"
                 aria-label="Session usage drawn as a character filling from the feet up"
                 data-alarm="{{ ($usage->fiveHourPercent ?? 0) >= 90 ? 'true' : 'false' }}">
                @foreach ($byRow as $row => $rowCells)
                    <g class="row" data-row="{{ $row }}"
                       data-lit="{{ $rowIsLit($usage->fiveHourPercent, $row) ? 'true' : 'false' }}"
                       style="--d: {{ ($bodyBottom - $row) * 26 }}ms">
                        @foreach ($rowCells as $cell)
                            <rect class="{{ $cell['eye'] ? 'eye' : 'body' }}"
                                  x="{{ $cell['x'] }}" y="{{ $cell['y'] }}" width="1" height="1"></rect>
                        @endforeach
                    </g>
                @endforeach
            </svg>
        </div>

        <div class="headline">
            <div class="value" id="five-hour-value">{{ $usage->fiveHourPercent === null ? '—' : round($usage->fiveHourPercent).'%' }}</div>
            <div class="caption">5-hour session &middot; <span id="five-hour-reset">—</span></div>
        </div>

        <div class="weekly">
            <div class="weekly-row">
                <span class="label">This week</span>
                <span class="value"><span id="seven-day-value">{{ $usage->sevenDayPercent === null ? '—' : round($usage->sevenDayPercent).'%' }}</span> &middot; <span id="seven-day-reset">—</span></span>
            </div>
            <div class="bar" id="seven-day-bar">
                @for ($segment = 0; $segment < 20; $segment++)
                    <i data-on="{{ ($usage->sevenDayPercent ?? 0) >= ($segment + 1) * 5 ? 'true' : 'false' }}"></i>
                @endfor
            </div>
        </div>

        <div class="notice" id="notice" data-visible="{{ $usage->error ? 'true' : 'false' }}">{{ $usage->error }}</div>

        <footer>
            <span id="updated">—</span>
            <button class="refresh" id="refresh" type="button">Refresh</button>
        </footer>
    </div>

    <script>
        const BODY_TOP = {{ $bodyTop }};
        const BODY_BOTTOM = {{ $bodyBottom }};

        const el = {
            panel: document.getElementById('panel'),
            mascot: document.getElementById('mascot'),
            status: document.getElementById('status'),
            statusText: document.getElementById('status-text'),
            fiveHourValue: document.getElementById('five-hour-value'),
            fiveHourReset: document.getElementById('five-hour-reset'),
            sevenDayValue: document.getElementById('seven-day-value'),
            sevenDayReset: document.getElementById('seven-day-reset'),
            sevenDayBar: document.getElementById('seven-day-bar'),
            notice: document.getElementById('notice'),
            updated: document.getElementById('updated'),
            refresh: document.getElementById('refresh'),
        };

        let usage = @json($usage);

        function severity(percent) {
            if (percent === null) return 'ok';
            if (percent >= 90) return 'critical';
            if (percent >= 75) return 'high';
            if (percent >= 50) return 'warn';
            return 'ok';
        }

        function percentText(percent) {
            return percent === null ? '—' : Math.round(percent) + '%';
        }

        /** "2h 14m", "6d 22h", "45s" - the largest two units that matter. */
        function countdown(iso) {
            if (!iso) return '—';
            const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
            if (Number.isNaN(seconds)) return '—';
            if (seconds <= 0) return 'resetting';

            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);

            if (days > 0) return 'resets in ' + days + 'd ' + hours + 'h';
            if (hours > 0) return 'resets in ' + hours + 'h ' + minutes + 'm';
            if (minutes > 0) return 'resets in ' + minutes + 'm';
            return 'resets in ' + seconds + 's';
        }

        function ago(iso) {
            const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
            if (Number.isNaN(seconds)) return '';
            if (seconds < 10) return 'Updated just now';
            if (seconds < 60) return 'Updated ' + seconds + 's ago';
            if (seconds < 3600) return 'Updated ' + Math.floor(seconds / 60) + 'm ago';
            return 'Updated ' + Math.floor(seconds / 3600) + 'h ago';
        }

        function render() {
            const fiveHour = usage.five_hour_percent;
            const sevenDay = usage.seven_day_percent;

            el.panel.dataset.severity = severity(fiveHour);
            el.mascot.dataset.alarm = fiveHour !== null && fiveHour >= 90 ? 'true' : 'false';

            el.fiveHourValue.textContent = percentText(fiveHour);
            el.sevenDayValue.textContent = percentText(sevenDay);

            // He fills from the feet up. Whole rows only: pixel art has no half row.
            const height = BODY_BOTTOM - BODY_TOP + 1;
            const litRows = fiveHour > 0 ? Math.round((Math.min(100, fiveHour) / 100) * height) : 0;

            el.mascot.querySelectorAll('.row').forEach((row) => {
                row.dataset.lit = Number(row.dataset.row) > BODY_BOTTOM - litRows ? 'true' : 'false';
            });

            el.sevenDayBar.querySelectorAll('i').forEach((segment, index) => {
                segment.dataset.on = (sevenDay || 0) >= (index + 1) * 5 ? 'true' : 'false';
            });

            el.status.dataset.stale = usage.is_stale ? 'true' : 'false';
            el.statusText.textContent = usage.is_stale ? 'Stale' : 'Live';

            el.notice.textContent = usage.error || '';
            el.notice.dataset.visible = usage.error ? 'true' : 'false';

            tick();
        }

        /** Clocks run locally so the countdown stays smooth between polls. */
        function tick() {
            el.fiveHourReset.textContent = countdown(usage.five_hour_resets_at);
            el.sevenDayReset.textContent = countdown(usage.seven_day_resets_at);
            el.updated.textContent = ago(usage.fetched_at);
        }

        el.refresh.addEventListener('click', async () => {
            el.refresh.disabled = true;
            el.refresh.textContent = 'Checking…';

            try {
                const response = await fetch(@json(route('dropdown.refresh')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });

                if (response.ok) {
                    usage = await response.json();
                    render();
                }
            } catch (error) {
                // The UsageUpdated broadcast is the real path; a failed click
                // just leaves the previous reading in place.
            } finally {
                el.refresh.disabled = false;
                el.refresh.textContent = 'Refresh';
            }
        });

        /**
         * Pushed from PHP after every poll. NativePHP strips the leading
         * backslash from the event class name before it reaches us.
         */
        window.Native?.on('App\\Events\\UsageUpdated', (payload) => {
            usage = payload.usage;
            render();
        });

        /**
         * The broadcast above is the instant path. This is the safety net: the
         * scheduler writes a new reading every minute, and if an IPC message
         * is ever missed the panel would otherwise sit on a stale one until
         * someone pressed Refresh. Reads the local cache only, so it is cheap
         * and never calls Anthropic.
         */
        async function syncFromCache() {
            try {
                const response = await fetch(@json(route('dropdown.usage')), {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store',
                });

                if (!response.ok) return;

                const fresh = await response.json();

                if (fresh.fetched_at !== usage.fetched_at || fresh.is_stale !== usage.is_stale) {
                    usage = fresh;
                    render();
                }
            } catch (error) {
                // Offline or mid restart; the next tick tries again.
            }
        }

        // Catch up the moment the panel is reopened, then keep checking while it is.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) syncFromCache();
        });
        window.addEventListener('focus', syncFromCache);

        setInterval(tick, 1000);
        setInterval(syncFromCache, 15000);
        render();
        syncFromCache();
    </script>
</body>
</html>
