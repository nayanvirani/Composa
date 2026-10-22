<?php

namespace App\Shopify;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Exchanges an App Bridge session token for an offline Admin API access token (Shopify managed
 * installation + token exchange, so install needs no OAuth redirects).
 */
final class TokenExchange
{
    /** @return array{access_token:string,scope:string} */
    public static function offline(string $shop, string $sessionToken): array
    {
        $response = Http::asJson()->acceptJson()->timeout(15)->retry(2, 300, throw: false)
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => config('composa.shopify.api_key'),
                'client_secret' => config('composa.shopify.api_secret'),
                'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
                'subject_token' => $sessionToken,
                'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token',
                'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
            ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Token exchange failed for '.$shop.' ('.$response->status().').');
        }

        return ['access_token' => (string) $response->json('access_token'), 'scope' => (string) $response->json('scope', '')];
    }
}
