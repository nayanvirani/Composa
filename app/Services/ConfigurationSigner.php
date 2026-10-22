<?php

namespace App\Services;

/**
 * Signs a configuration's price so the Cart Transform Function can trust it at checkout.
 *
 * Message (v1): "v1|{shop}|{config_id}|{price_cents}|{base_cents}|{currency}|{components}"
 * where components = "variantId:cents" pairs joined by ","; the signature is hex HMAC-SHA256
 * with the shop's secret, which the function reads from its own app-owned metafield.
 * Twin: extensions/composa-cart-transform/src/signature.js — tests/engine/signing-vectors.json.
 */
final class ConfigurationSigner
{
    /** @param list<array{variant_id:string,cents:int}> $components */
    public static function message(string $shop, string $configId, int $priceCents, int $baseCents, string $currency, array $components = []): string
    {
        $parts = array_map(fn ($c) => self::numericId((string) $c['variant_id']).':'.(int) $c['cents'], $components);

        return implode('|', ['v1', $shop, $configId, $priceCents, $baseCents, strtoupper($currency), implode(',', $parts)]);
    }

    public static function sign(string $secret, string $message): string
    {
        return hash_hmac('sha256', $message, $secret);
    }

    public static function verify(string $secret, string $message, string $signature): bool
    {
        return hash_equals(self::sign($secret, $message), strtolower($signature));
    }

    /** Compact components for the hidden _lnk line property: "id:cents,id:cents". */
    public static function encodeComponents(array $components): string
    {
        return implode(',', array_map(fn ($c) => self::numericId((string) $c['variant_id']).':'.(int) $c['cents'], $components));
    }

    public static function numericId(string $gid): string
    {
        $tail = strrchr($gid, '/');

        return $tail === false ? $gid : substr($tail, 1);
    }
}
