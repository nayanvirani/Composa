<?php

return [

    /*
    | Shopify app credentials and settings. Fill these in from the Partner Dashboard / shopify.app.toml.
    */
    'shopify' => [
        'api_key' => env('SHOPIFY_API_KEY'),
        'api_secret' => env('SHOPIFY_API_SECRET'),
        'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
        'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products,read_themes,write_cart_transforms,read_orders,write_orders,write_files,read_locales,read_markets'),
        'app_handle' => env('SHOPIFY_APP_HANDLE', 'composa'),
        // App Proxy: Shopify forwards https://{shop}/{prefix}/{subpath}/* to APP_URL/proxy/*
        'proxy_prefix' => env('SHOPIFY_PROXY_PREFIX', 'apps'),
        'proxy_subpath' => env('SHOPIFY_PROXY_SUBPATH', 'composa'),
        'cart_transform_function' => env('SHOPIFY_CART_TRANSFORM_HANDLE', 'composa-cart-transform'),
        'discount_function' => env('SHOPIFY_DISCOUNT_FUNCTION_HANDLE', 'composa-price-fallback'),
        'theme_extension_handle' => env('SHOPIFY_THEME_EXTENSION_HANDLE', 'composa-theme'),
        'metafield_namespace' => 'composa',
    ],

    /*
    | Cart mode. update = lineUpdate (Shopify Plus and development stores), expand = lineExpand into the
    | line's own variant (every other plan), discount = fallback that lists the base variant at its
    | highest price and lowers it with a Discount Function. No mode ever creates hidden products.
    */
    'cart' => [
        'default_mode' => env('COMPOSA_CART_MODE', 'expand'),
        'force_discount_fallback' => (bool) env('COMPOSA_FORCE_DISCOUNT_FALLBACK', false),
        'configuration_ttl_days' => 30,
    ],

    'uploads' => [
        'disk' => env('COMPOSA_UPLOAD_DISK', 's3'),
        'max_mb' => (int) env('COMPOSA_UPLOAD_MAX_MB', 20),
        'allowed' => ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf', 'svg', 'ai', 'eps'],
        'put_ttl_minutes' => 15,
        'get_ttl_minutes' => 10,
        'abandoned_days' => 30,
    ],

    'limits' => [
        // Warn merchants when any usage reaches this share of the plan limit.
        'warn_at' => 0.8,
        // After a downgrade, extra published configurators move to draft after this grace period.
        'downgrade_grace_days' => 7,
    ],

    'uninstall_purge_hours' => 48,

    'admin' => [
        'domain' => env('ADMIN_DOMAIN'),
        'path' => env('ADMIN_PATH', 'admin'),
        'google_sso' => (bool) env('ADMIN_GOOGLE_SSO', false),
        'allowed_email_domains' => array_filter(explode(',', (string) env('ADMIN_ALLOWED_EMAIL_DOMAINS', ''))),
        'impersonation_minutes' => 30,
    ],

    'storefront' => [
        // Target for "popup interactive" on a 4G connection; slower loads are reported to monitoring.
        'slow_load_ms' => 1500,
    ],
];
