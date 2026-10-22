<?php

namespace App\View;

use App\Models\Announcement;
use App\Models\NavigationMenu;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Data every website view needs: settings, menus and the active site announcement. */
class SiteComposer
{
    public function compose(View $view): void
    {
        $menus = Cache::remember('site_menus', 300, fn () => NavigationMenu::query()->get()->mapWithKeys(fn ($m) => [$m->key => $m->items ?? []])->all());
        $announcement = Cache::remember('site_announcement', 60, fn () => Announcement::query()->where('scope', 'site')->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest()->first());

        $view->with([
            'site' => [
                'brand' => SiteSetting::get('brand', []) + ['name' => 'Composa'],
                'contact' => SiteSetting::get('contact', []),
                'links' => SiteSetting::get('links', []) + ['install' => 'https://apps.shopify.com/composa'],
                'seo' => SiteSetting::get('seo', []),
                'analytics' => SiteSetting::get('analytics', []),
                'company' => SiteSetting::get('company', []),
                'footer' => SiteSetting::get('footer', []),
            ],
            'menus' => $menus,
            'announcement' => $announcement,
        ]);
    }
}
