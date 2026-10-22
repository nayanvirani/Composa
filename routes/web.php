<?php

use Illuminate\Support\Facades\Route;

/*
| Public website and super admin sign-in. The merchant app lives in routes/app.php (prefix /app),
| the storefront App Proxy in routes/proxy.php (prefix /proxy), webhooks in bootstrap/app.php.
*/

// App Bridge bounce page: reloads the shopify-reload URL with a fresh session token. Must stay
// outside the authenticated /app group.
Route::get('/app/bounce', fn () => view('app.bounce', ['apiKey' => config('composa.shopify.api_key')]))->name('app.bounce');

require __DIR__.'/site.php';
