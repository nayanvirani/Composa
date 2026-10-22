<?php

use App\Http\Middleware\AuthenticateShopify;
use App\Http\Middleware\BlockReadOnlyImpersonation;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyAppProxy;
use App\Http\Middleware\VerifyShopifyWebhook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Embedded merchant app (Shopify admin iframe): session-token auth, no CSRF cookie.
            Route::middleware('embedded')->prefix('app')->name('app.')->group(base_path('routes/app.php'));
            // Storefront through the Shopify App Proxy: signed query, no session.
            Route::middleware('proxy')->prefix('proxy')->name('proxy.')->group(base_path('routes/proxy.php'));
            // Shopify webhooks: HMAC-verified.
            Route::middleware(VerifyShopifyWebhook::class)->post('webhooks/shopify', App\Http\Controllers\WebhookController::class)->name('webhooks.shopify');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->group('embedded', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateShopify::class,
            BlockReadOnlyImpersonation::class,
            HandleInertiaRequests::class,
            SubstituteBindings::class,
        ]);
        $middleware->group('proxy', [
            VerifyAppProxy::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('app/api/*', 'proxy/*', 'webhooks/*') || $request->expectsJson(),
        );
    })->create();
