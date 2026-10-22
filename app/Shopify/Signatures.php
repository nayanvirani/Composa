<?php

namespace App\Shopify;

/** Verifies requests Shopify signs with the app secret: webhooks and App Proxy calls. */
final class Signatures
{
    /** X-Shopify-Hmac-Sha256: base64 HMAC-SHA256 of the raw body. */
    public static function validWebhook(string $rawBody, ?string $hmacHeader, ?string $secret = null): bool
    {
        $secret ??= (string) config('composa.shopify.api_secret');
        if ($hmacHeader === null || $hmacHeader === '' || $secret === '') {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $rawBody, $secret, true)), $hmacHeader);
    }

    /**
     * App Proxy: `signature` is the hex HMAC-SHA256 of every other query parameter, sorted by key,
     * joined as key=value (array values joined by commas) with no separator between pairs.
     *
     * @param  array<string,mixed>  $query
     */
    public static function validProxy(array $query, ?string $secret = null): bool
    {
        $secret ??= (string) config('composa.shopify.api_secret');
        $signature = $query['signature'] ?? null;
        if (! is_string($signature) || $signature === '' || $secret === '') {
            return false;
        }
        unset($query['signature']);
        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = $key.'='.(is_array($value) ? implode(',', $value) : $value);
        }
        sort($pairs);

        return hash_equals(hash_hmac('sha256', implode('', $pairs), $secret), $signature);
    }
}
