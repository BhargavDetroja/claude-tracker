---
paths:
  - 'app/Services/**'
---

# Services

## Menu bar icons are files, and storage_path() moves at runtime
Electron's Tray takes a file path, not a data URI, so ring icons are rendered to disk with GD and handed to MenuBar::icon() by path. macOS treats an image as a template (auto-tinting for light/dark) purely from the filename ending in "Template.png"; the @2x variant sits beside it and Electron picks it up for Retina on its own.

When the desktop app is running, NativePHP rewrites storage_path() to ~/Library/Application Support/nativephp/storage, so generated icons land there, not in the project's storage/. Don't go looking for them in the repo.

MenuBar::icon()/label()/tooltip() POST to the Electron process and fail outside it, so UsagePoller guards them with config('nativephp-internal.running'). That flag is set for scheduler and queue child processes too, so scheduled polls still paint the menu bar. Keep the guard or `php artisan claude:poll-usage` breaks in a plain terminal.
