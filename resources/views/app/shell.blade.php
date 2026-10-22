<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="shopify-api-key" content="{{ config('composa.shopify.api_key') }}">
<title>Composa</title>
{{-- App Bridge: session tokens for every fetch, navigation menu, toasts and the resource picker. --}}
<script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
@viteReactRefresh
@vite(['resources/js/app.jsx'])
<x-inertia::head />
</head>
<body>
<x-inertia::app />
</body>
</html>
