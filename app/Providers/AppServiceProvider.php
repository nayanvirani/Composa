<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\NavigationMenu;
use App\View\SiteComposer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['site.layout', 'site.page', 'site.legal', 'site.docs', 'site.doc', 'site.journal', 'site.post', 'site.404'], SiteComposer::class);

        NavigationMenu::saved(fn () => Cache::forget('site_menus'));
        NavigationMenu::deleted(fn () => Cache::forget('site_menus'));
        Announcement::saved(fn () => Cache::forget('site_announcement'));
        Announcement::deleted(fn () => Cache::forget('site_announcement'));
    }
}
