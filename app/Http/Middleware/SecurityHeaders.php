<?php

namespace App\Http\Middleware;

use App\Shopify\ShopDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers. Pages under /app (the embedded merchant app, its bounce page and its
 * error responses) may be framed by Shopify admin; everything else only by this site.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', 'frame-ancestors '.$this->frameAncestors($request));
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function frameAncestors(Request $request): string
    {
        if (! $request->is('app', 'app/*')) {
            return "'self'";
        }
        $shop = ShopDomain::normalize((string) $request->query('shop'));

        return ($shop ? 'https://'.$shop : 'https://*.myshopify.com').' https://admin.shopify.com';
    }
}
