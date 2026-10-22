<?php

use App\Http\Controllers\Proxy\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
| Storefront through the Shopify App Proxy: https://{shop}/apps/composa/* → APP_URL/proxy/*.
| Middleware "proxy" verifies Shopify's signature and resolves the shop.
*/

Route::get('configurators/{id}', [StorefrontController::class, 'configurator'])->whereNumber('id')->name('configurator');
Route::post('price', [StorefrontController::class, 'price'])->name('price');
Route::post('configurations', [StorefrontController::class, 'createConfiguration'])->name('configurations.store');
Route::post('uploads', [StorefrontController::class, 'presignUpload'])->name('uploads.presign');
Route::post('uploads/{id}/complete', [StorefrontController::class, 'completeUpload'])->name('uploads.complete');
Route::post('events', [StorefrontController::class, 'events'])->name('events');
Route::get('configure/{handle}', [StorefrontController::class, 'page'])->name('page');
Route::get('image/{configId}.png', [StorefrontController::class, 'image'])->name('image');
