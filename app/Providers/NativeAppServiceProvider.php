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
         * Take a first reading straight away rather than waiting up to a
         * minute for the scheduler. It runs as a child process because the
         * very first Keychain read raises a macOS access prompt, and blocking
         * boot on that would leave the menu bar item unresponsive behind it.
         */
        ChildProcess::artisan(['claude:poll-usage'], 'initial-usage-poll');
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [];
    }
}
