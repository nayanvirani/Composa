<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Shopify\ShopDomain;
use App\Shopify\Signatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront requests through the Shopify App Proxy (https://{shop}/apps/composa/*). Shopify signs
 * the query string; the shop is taken only from that signed query, never from the body.
 */
class VerifyAppProxy
{
    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query();
        if (! Signatures::validProxy($query)) {
            return response()->json(['message' => 'Invalid proxy signature'], 401);
        }
        if (abs(time() - (int) ($query['timestamp'] ?? 0)) > 3600) {
            return response()->json(['message' => 'Stale proxy request'], 401);
        }
        $domain = ShopDomain::normalize($query['shop'] ?? null);
        $shop = $domain ? Shop::query()->with('plan')->where('domain', $domain)->first() : null;
        if (! $shop || ! $shop->isActive()) {
            return response()->json(['message' => 'Shop not found'], 404);
        }

        $request->attributes->set('shop', $shop);
        $request->attributes->set('customer_id', $query['logged_in_customer_id'] ?? null);
        app()->instance(Shop::class, $shop);

        $response = $next($request);
        // Proxied responses must never be cached across shoppers.
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
