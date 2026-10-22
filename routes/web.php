<?php

use App\Http\Controllers\Admin\GoogleAuthController;
use Illuminate\Support\Facades\Route;

/*
| Public website and super admin sign-in. The merchant app lives in routes/app.php (prefix /app),
| the storefront App Proxy in routes/proxy.php (prefix /proxy), webhooks in bootstrap/app.php.
*/

// App Bridge bounce page: reloads the shopify-reload URL with a fresh session token. Must stay
// outside the authenticated /app group.
Route::get('/app/bounce', fn () => view('app.bounce', ['apiKey' => config('composa.shopify.api_key')]))->name('app.bounce');

// Super admin Google sign-in (the Filament panel itself is registered by AdminPanelProvider).
Route::prefix(config('composa.admin.path', 'admin'))->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('admin.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('admin.google.callback');
});

require __DIR__.'/site.php';
