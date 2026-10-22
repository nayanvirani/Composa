<?php

return [
    // The merchant app renders client-side inside the Shopify admin iframe; no SSR service.
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'ensure_bundle_exists' => false,
        'throw_on_error' => false,
    ],

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/pages')],
        'extensions' => ['js', 'jsx', 'ts', 'tsx'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'ts', 'tsx'],
    ],

    'history' => [
        'encrypt' => false,
    ],
];
