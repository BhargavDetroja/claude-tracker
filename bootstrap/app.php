<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/**
 * Keep Laravel from writing inside the signed .app bundle.
 *
 * When NativePHP ships the app, this Laravel install lives inside a code
 * signed bundle. On first boot Laravel caches config, events and routes into
 * bootstrap/cache. Writing a single byte inside the bundle invalidates the
 * code signature, and macOS then refuses to open the app, reporting it as
 * damaged or as malware. The app effectively destroys its own signature the
 * first time it runs.
 *
 * Redirecting those three caches to the per user storage directory keeps the
 * bundle byte for byte as it was signed. Outside the desktop app the variable
 * is absent and Laravel uses bootstrap/cache as normal.
 *
 * NativePHP already does this itself, but only for a "secure" bundled build.
 * An unsecure build ships plain source files and gets no such redirect, so
 * anything already set here is left alone and this only fills the gap.
 */
if ($nativeStoragePath = getenv('NATIVEPHP_STORAGE_PATH')) {
    $bootstrapCachePath = $nativeStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'bootstrap';

    if (! is_dir($bootstrapCachePath)) {
        @mkdir($bootstrapCachePath, 0755, true);
    }

    if (is_writable($bootstrapCachePath)) {
        foreach ([
            'APP_CONFIG_CACHE' => 'config.php',
            'APP_EVENTS_CACHE' => 'events.php',
            'APP_ROUTES_CACHE' => 'routes-v7.php',
        ] as $variable => $file) {
            if (getenv($variable) !== false) {
                continue;
            }

            $path = $bootstrapCachePath.DIRECTORY_SEPARATOR.$file;

            $_ENV[$variable] = $_SERVER[$variable] = $path;
            putenv("{$variable}={$path}");
        }
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
