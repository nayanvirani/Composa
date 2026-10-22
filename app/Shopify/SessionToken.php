<?php

namespace App\Shopify;

use App\Support\Jwt;
use RuntimeException;

/**
 * App Bridge session tokens (HS256 JWTs signed with the app secret). The embedded merchant app
 * sends one on every request; it proves which shop and staff member is calling.
 */
final class SessionToken
{
    /**
     * @return array{shop:string,user_id:?string,payload:array<string,mixed>}
     *
     * @throws RuntimeException when the token is invalid for this app
     */
    public static function verify(string $token, ?string $apiKey = null, ?string $secret = null): array
    {
        $apiKey ??= (string) config('composa.shopify.api_key');
        $secret ??= (string) config('composa.shopify.api_secret');
        if ($secret === '' || $apiKey === '') {
            throw new RuntimeException('Shopify app credentials are not configured.');
        }

        $payload = Jwt::decode($token, $secret, leeway: 10);

        if (($payload['aud'] ?? null) !== $apiKey) {
            throw new RuntimeException('Session token was issued for another app.');
        }
        if (isset($payload['nbf']) && $payload['nbf'] - 10 > time()) {
            throw new RuntimeException('Session token is not valid yet.');
        }
        $shop = ShopDomain::normalize((string) ($payload['dest'] ?? ''));
        $issuer = ShopDomain::normalize(preg_replace('#/admin/?$#', '', (string) ($payload['iss'] ?? '')));
        if ($shop === null || $issuer !== $shop) {
            throw new RuntimeException('Session token has an invalid shop.');
        }

        return ['shop' => $shop, 'user_id' => isset($payload['sub']) ? (string) $payload['sub'] : null, 'payload' => $payload];
    }
}
