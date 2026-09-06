<?php

namespace App\Providers;

use App\Services\MenuBarIconRenderer;
use App\Services\UsagePoller;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\ChildProcess;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\MenuBar;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     *
     * There is no main window: the whole app is the menu bar item and the
     * panel that drops out of it.
     */
    public function boot(): void
    {
        $usage = app(UsagePoller::class)->current();

        MenuBar::create()
            ->route('dropdown')
            ->icon(app(MenuBarIconRenderer::class)->render($usage->headlinePercent()))
            ->label($usage->menuBarLabel())
            ->tooltip($usage->tooltip())
            ->width(340)
            ->height(430)
            ->resizable(false)
            ->withContextMenu(Menu::make(
                Menu::label('Claude Tracker'),
                Menu::separator(),
                Menu::quit('Quit Claude Tracker'),
            ));

        /**
         * The app's polling lives here, in a process NativePHP supervises and
         * restarts, rather than in the Laravel scheduler alone.
         *
         * The scheduler is driven by a timer in Electron's main process, which
         * macOS App Nap can throttle for a menu bar app with no visible window,
         * and which NativePHP stops on sleep. A child process is not the UI
         * process, so it avoids both. It also takes the first reading
         * immediately, which the scheduler alone would delay by up to a minute.
         *
         * It runs as a child process for one more reason: the very first
         * Keychain read raises a macOS access prompt, and blocking boot on that
         * would leave the menu bar item unresponsive behind it.
         */
        ChildProcess::artisan(['claude:watch'], 'usage-watcher', persistent: true);
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [];
    }
}
