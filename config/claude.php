<?php

return [
    /**
     * The macOS Keychain generic-password service that Claude Code stores
     * its OAuth credentials under. Read with `security find-generic-password`.
     */
    'keychain_service' => env('CLAUDE_KEYCHAIN_SERVICE', 'Claude Code-credentials'),

    /**
     * The undocumented usage endpoint Claude Code itself calls. This is not a
     * published API: treat every field as optional and expect it to change
     * or disappear without notice.
     */
    'usage_endpoint' => env('CLAUDE_USAGE_ENDPOINT', 'https://api.anthropic.com/api/oauth/usage'),

    /**
     * Sent as the `anthropic-beta` header alongside the OAuth bearer token.
     */
    'oauth_beta' => env('CLAUDE_OAUTH_BETA', 'oauth-2025-04-20'),

    'request_timeout' => (int) env('CLAUDE_REQUEST_TIMEOUT', 10),

    /**
     * Which window drives the menu bar ring and label.
     * Supported: "five_hour", "seven_day", "highest".
     */
    'menu_bar_metric' => env('CLAUDE_MENU_BAR_METRIC', 'five_hour'),

    /**
     * Seconds between checks in the background watcher.
     */
    'poll_interval' => (int) env('CLAUDE_POLL_INTERVAL', 60),

    /**
     * A reading younger than this is considered fresh enough to skip.
     *
     * Two things poll: the supervised watcher and the scheduler. Whichever
     * fires first does the work and the other becomes a no-op, so the app is
     * covered if either mechanism stalls without doubling the API calls.
     */
    'stale_after' => (int) env('CLAUDE_STALE_AFTER', 45),

    /**
     * Where the last good reading is kept so the UI can degrade to
     * "last known" instead of blanking when a poll fails.
     */
    'cache_key' => 'claude-tracker.snapshot',

    'icon' => [
        /**
         * Where generated menu bar icons are cached. Overridden in tests so a
         * test run never deletes the icons the running app is pointing at.
         */
        'path' => env('CLAUDE_ICON_PATH', storage_path('app/menubar')),

        /**
         * Logical size in points. macOS menu bars are 22pt tall; 16pt leaves
         * the standard breathing room above and below.
         */
        'size' => 16,

        /**
         * Percentages are rounded to this bucket before rendering so the
         * icon cache stays small (100 / 5 = 21 files, times two for @2x).
         */
        'bucket' => 5,
    ],
];
