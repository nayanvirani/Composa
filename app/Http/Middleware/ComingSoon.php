<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the app is listed on the Shopify App Store, the public website shows a coming-soon page.
 * Switched in super admin (Website → Site settings → Launch). Legal pages stay public because the
 * App Store listing links to them; the signup forms keep working; signed-in staff see the full site.
 */
class ComingSoon
{
    private const OPEN = ['legal/*', 'forms/*', 'robots.txt', 'status'];

    public function handle(Request $request, Closure $next): Response
    {
        $launch = SiteSetting::get('launch', []);
        if (empty($launch['coming_soon']) || $request->is(...self::OPEN) || $request->user()?->canAccessPanel(filament()->getPanel('admin'))) {
            return $next($request);
        }

        return response()->view('site.coming-soon', ['launch' => $launch], 200, ['X-Robots-Tag' => 'noindex']);
    }
}
