<?php

namespace App\Http\Middleware;

use App\Shopify\ShopDomain;
use App\Shopify\Signatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Signatures::validWebhook($request->getContent(), $request->header('X-Shopify-Hmac-Sha256'))) {
            return response('Invalid webhook signature', 401);
        }
        if (ShopDomain::normalize($request->header('X-Shopify-Shop-Domain')) === null) {
            return response('Invalid shop', 400);
        }

        return $next($request);
    }
}
