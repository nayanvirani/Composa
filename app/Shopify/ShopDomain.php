<?php

namespace App\Shopify;

/** Normalises and strictly validates *.myshopify.com domains before they are trusted. */
final class ShopDomain
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $domain = strtolower(trim($value));
        $domain = preg_replace('#^https?://#', '', $domain) ?? '';
        $domain = rtrim(explode('/', $domain)[0], '.');

        return self::isValid($domain) ? $domain : null;
    }

    public static function isValid(string $domain): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{0,60}\.myshopify\.com$/', $domain);
    }
}
