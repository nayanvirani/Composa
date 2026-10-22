<?php

use App\Http\Controllers\Site\FormController;
use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

/*
| Public website. Pages, posts and docs are managed in super admin; the catch-all route at the end
| serves any CMS page by slug (optionally prefixed with a locale such as /fr/pricing).
*/

Route::get('sitemap.xml', [SiteController::class, 'sitemap'])->name('site.sitemap');
Route::get('robots.txt', [SiteController::class, 'robots'])->name('site.robots');

Route::get('docs', [SiteController::class, 'docs'])->name('site.docs');
Route::get('docs/{slug}', [SiteController::class, 'doc'])->where('slug', '[a-z0-9\-\/]+')->name('site.doc');
Route::post('docs/{slug}/vote', [SiteController::class, 'docVote'])->middleware('throttle:30,1')->name('site.doc.vote');
Route::get('journal', [SiteController::class, 'journal'])->name('site.journal');
Route::get('journal/{slug}', [SiteController::class, 'post'])->name('site.post');

Route::post('forms/contact', [FormController::class, 'contact'])->name('site.forms.contact');
Route::post('forms/beta', [FormController::class, 'beta'])->name('site.forms.beta');
Route::post('forms/newsletter', [FormController::class, 'newsletter'])->name('site.forms.newsletter');

Route::get('/', [SiteController::class, 'page'])->name('site.home');
Route::get('{locale}/{slug?}', [SiteController::class, 'page'])
    ->where(['locale' => '(?!admin|app|proxy|webhooks|horizon|livewire|filament|storage|build)[a-z0-9\-]+', 'slug' => '[a-z0-9\-\/]+'])
    ->name('site.page');
