<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the app is listed on the Shopify App Store, the public website shows a coming-soon page.
 * Switched in super admin (Website → Site settings → Launch). Legal pages stay public because the
 * App Store listing links to them and the signup forms keep working. Signed-in staff can preview the
 * full site with ?preview=full (and leave it with ?preview=off).
 */
class ComingSoon
{
    private const OPEN = ['legal/*', 'forms/*', 'robots.txt', 'status'];

    public function handle(Request $request, Closure $next): Response
    {
        $launch = SiteSetting::get('launch', []);
        if (empty($launch['coming_soon']) || $request->is(...self::OPEN)) {
            return $next($request);
        }

        $staff = (bool) $request->user()?->canAccessPanel(filament()->getPanel('admin'));
        if ($staff && $request->has('preview')) {
            $request->session()->put('site_preview', $request->query('preview') === 'full');
        }
        if ($staff && $request->session()->get('site_preview')) {
            view()->share('sitePreview', true);

            return $next($request);
        }
        view()->share('canPreview', $staff);

        return response()->view('site.coming-soon', ['launch' => $launch], 200, ['X-Robots-Tag' => 'noindex']);
    }
}
