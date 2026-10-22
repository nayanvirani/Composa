<?php

use App\Http\Controllers\App\AnalyticsController;
use App\Http\Controllers\App\AssignmentController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\ConfiguratorController;
use App\Http\Controllers\App\HomeController;
use App\Http\Controllers\App\OnboardingController;
use App\Http\Controllers\App\OrderController;
use App\Http\Controllers\App\ProductController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\SupportController;
use App\Http\Controllers\App\TranslationController;
use Illuminate\Support\Facades\Route;

/*
| Embedded merchant app (prefix /app, middleware "embedded": session token, shop scope).
| Pages render with Inertia; /app/api/* returns JSON for the builder's saves and pickers.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('onboarding/welcome', [OnboardingController::class, 'welcome'])->name('onboarding.welcome');
Route::get('onboarding/template', [OnboardingController::class, 'template'])->name('onboarding.template');
Route::get('onboarding/embed', [OnboardingController::class, 'embed'])->name('onboarding.embed');

Route::get('configurators', [ConfiguratorController::class, 'index'])->name('configurators.index');
Route::get('configurators/new', [ConfiguratorController::class, 'create'])->name('configurators.create');
Route::get('configurators/{configurator}', [ConfiguratorController::class, 'show'])->whereNumber('configurator')->name('configurators.show');

Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');
Route::get('translations', [TranslationController::class, 'index'])->name('translations');
Route::get('settings', [SettingsController::class, 'index'])->name('settings');
Route::get('billing', BillingController::class)->name('billing');
Route::get('support', [SupportController::class, 'index'])->name('support');

Route::prefix('api')->name('api.')->group(function () {
    Route::post('onboarding', [OnboardingController::class, 'update'])->name('onboarding');
    Route::post('onboarding/plan', [OnboardingController::class, 'syncPlan'])->name('onboarding.plan');
    Route::post('theme/check', [OnboardingController::class, 'checkTheme'])->name('theme.check');

    Route::post('configurators', [ConfiguratorController::class, 'store'])->name('configurators.store');
    Route::put('configurators/{configurator}/draft', [ConfiguratorController::class, 'saveDraft'])->name('configurators.draft');
    Route::get('configurators/{configurator}/checks', [ConfiguratorController::class, 'checks'])->name('configurators.checks');
    Route::post('configurators/{configurator}/publish', [ConfiguratorController::class, 'publish'])->name('configurators.publish');
    Route::post('configurators/{configurator}/unpublish', [ConfiguratorController::class, 'unpublish'])->name('configurators.unpublish');
    Route::post('configurators/{configurator}/duplicate', [ConfiguratorController::class, 'duplicate'])->name('configurators.duplicate');
    Route::delete('configurators/{configurator}', [ConfiguratorController::class, 'destroy'])->name('configurators.destroy');
    Route::post('configurators/{configurator}/versions/{version}/restore', [ConfiguratorController::class, 'restore'])->whereNumber('version')->name('configurators.restore');
    Route::post('configurators/{configurator}/evaluate', [ConfiguratorController::class, 'evaluate'])->name('configurators.evaluate');
    Route::post('configurators/{configurator}/variants', [ConfiguratorController::class, 'importVariants'])->name('configurators.variants');

    Route::post('configurators/{configurator}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::put('configurators/{configurator}/assignments/order', [AssignmentController::class, 'reorder'])->name('assignments.reorder');
    Route::delete('configurators/{configurator}/assignments/{assignment}', [AssignmentController::class, 'destroy'])->whereNumber('assignment')->name('assignments.destroy');

    Route::put('configurators/{configurator}/translations', [TranslationController::class, 'update'])->name('translations.update');

    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('products/show', [ProductController::class, 'show'])->name('products.show');
    Route::post('images', [ProductController::class, 'uploadImage'])->name('images.upload');

    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->whereNumber('order')->name('orders.status');
    Route::get('orders/{order}/pdf', [OrderController::class, 'pdf'])->whereNumber('order')->name('orders.pdf');
    Route::get('orders/{order}/files/{upload}', [OrderController::class, 'file'])->whereNumber('order')->name('orders.file');

    Route::get('analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('support', [SupportController::class, 'store'])->name('support.store');
});
