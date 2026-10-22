<?php

namespace App\Http\Middleware;

use App\Models\Impersonation;
use App\Models\Shop;
use App\Services\ShopInstaller;
use App\Shopify\SessionToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Authenticates the embedded merchant app with App Bridge session tokens: `id_token` in the URL on
 * the first document load, `Authorization: Bearer` on every request after. Unknown or uninstalled
 * shops are installed on the spot (token exchange). Super admin impersonation sessions are also
 * accepted here; they are time-boxed and read-only unless granted write access.
 */
class AuthenticateShopify
{
    public function __construct(private readonly ShopInstaller $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($shop = $this->fromImpersonation($request)) {
            return $this->continue($request, $next, $shop);
        }

        $token = $request->bearerToken() ?: $request->query('id_token');
        if (! is_string($token) || $token === '') {
            return $this->missingToken($request);
        }

        try {
            $session = SessionToken::verify($token);
        } catch (RuntimeException $e) {
            return $this->invalidToken($request, $e->getMessage());
        }

        $shop = Shop::query()->with('plan')->where('domain', $session['shop'])->first();
        if (! $shop || ! $shop->isActive()) {
            try {
                $shop = $this->installer->install($session['shop'], $token)->load('plan');
            } catch (Throwable $e) {
                Log::error('Install failed for '.$session['shop'].': '.$e->getMessage());

                return response('Composa could not finish installing. Please reload the app.', 503);
            }
        }

        $request->attributes->set('shopify_user_id', $session['user_id']);

        return $this->continue($request, $next, $shop);
    }

    private function continue(Request $request, Closure $next, Shop $shop): Response
    {
        $request->attributes->set('shop', $shop);
        app()->instance(Shop::class, $shop);

        $response = $next($request);
        $response->headers->set('Content-Security-Policy', "frame-ancestors https://{$shop->domain} https://admin.shopify.com;");

        return $response;
    }

    private function fromImpersonation(Request $request): ?Shop
    {
        if (! $request->hasSession()) {
            return null;
        }
        if ($raw = $request->query('impersonate')) {
            $session = Impersonation::query()->where('token_hash', hash('sha256', (string) $raw))->whereNull('ended_at')->where('expires_at', '>', now())->first();
            if ($session) {
                $request->session()->put('impersonation_id', $session->id);
            }
        }
        $id = $request->session()->get('impersonation_id');
        if (! $id) {
            return null;
        }
        $session = Impersonation::query()->with('shop.plan')->find($id);
        if (! $session || $session->ended_at || $session->expires_at->isPast()) {
            $request->session()->forget('impersonation_id');

            return null;
        }
        $request->attributes->set('impersonation', $session);

        return $session->shop;
    }

    private function missingToken(Request $request): Response
    {
        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return response()->json(['message' => 'Missing session token'], 401)->header('X-Shopify-Retry-Invalid-Session-Request', '1');
        }

        return $this->bounce($request);
    }

    /**
     * Document load without a usable token (e.g. a reload): go through the bounce page, where App
     * Bridge reloads the `shopify-reload` URL with a fresh id_token.
     */
    private function bounce(Request $request): Response
    {
        $target = $request->fullUrlWithoutQuery(['id_token']);

        return redirect()->route('app.bounce', ['shopify-reload' => $target]);
    }

    private function invalidToken(Request $request, string $reason): Response
    {
        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return response()->json(['message' => 'Invalid session token', 'reason' => $reason], 401)->header('X-Shopify-Retry-Invalid-Session-Request', '1');
        }

        return $this->bounce($request);
    }
}
