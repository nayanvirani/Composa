@php
    use App\Support\SiteText;
    $brand = $site['brand'];
    $suffix = $site['seo']['title_suffix'] ?? ' — '.$brand['name'];
    $metaTitle = ($seo['title'] ?? null) ?: (($title ?? null) ? $title.$suffix : $brand['name'].' — '.($brand['tagline'] ?? 'Product configurator for Shopify'));
    $metaDescription = ($seo['description'] ?? null) ?: ($description ?? null) ?: ($site['seo']['description'] ?? null);
    $metaImage = ($seo['image'] ?? null) ?: ($site['seo']['image'] ?? null);
    $install = $site['links']['install'];
    $header = $menus['header'] ?? [['label' => 'Features', 'url' => '/features'], ['label' => 'Templates', 'url' => '/templates'], ['label' => 'Pricing', 'url' => '/pricing'], ['label' => 'Docs', 'url' => '/docs'], ['label' => 'Journal', 'url' => '/journal']];
    $path = '/'.ltrim(request()->path(), '/');
@endphp
<!doctype html>
<html lang="{{ $locale ?? 'en' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $metaTitle }}</title>
@if ($metaDescription)<meta name="description" content="{{ $metaDescription }}">@endif
@if (($seo['robots'] ?? 'index') === 'noindex')<meta name="robots" content="noindex">@endif
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:title" content="{{ $metaTitle }}">
@if ($metaDescription)<meta property="og:description" content="{{ $metaDescription }}">@endif
@if ($metaImage)<meta property="og:image" content="{{ $metaImage }}"><meta name="twitter:card" content="summary_large_image">@endif
@if (! empty($brand['favicon_url']))<link rel="icon" href="{{ $brand['favicon_url'] }}">@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=Instrument+Serif:ital@0;1&display=swap">
@vite(['resources/css/site.css'])
@if (! empty($site['analytics']['plausible_domain']))
<script defer data-domain="{{ $site['analytics']['plausible_domain'] }}" src="https://plausible.io/js/script.js"></script>
@endif
@if (! empty($site['analytics']['ga4_id']))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $site['analytics']['ga4_id'] }}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',@json($site['analytics']['ga4_id']));</script>
@endif
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

@if ($announcement)
<div class="notice" role="region" aria-label="Announcement">
    <div class="wrap">
        <span>{{ $announcement->message }}</span>
        @if ($announcement->link_url)<a href="{{ SiteText::url($announcement->link_url) }}">{{ $announcement->link_label ?: 'Read more' }} →</a>@endif
    </div>
</div>
@endif

<header class="site-head">
    <div class="wrap">
        <a class="brand" href="/" aria-label="{{ $brand['name'] }} home">
            @if (! empty($brand['logo_url']))
                <img src="{{ $brand['logo_url'] }}" alt="">
            @else
                <span class="brand-mark" aria-hidden="true">C</span>
            @endif
            <span>{{ $brand['name'] }}</span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menu" onclick="var n=document.getElementById('site-nav');var o=n.classList.toggle('is-open');this.setAttribute('aria-expanded',o)"><span></span><span></span><span></span></button>
        <nav class="nav" id="site-nav" aria-label="Main">
            @foreach ($header as $item)
                <a href="{{ SiteText::url($item['url']) }}" @if ($path === $item['url'] || ($item['url'] !== '/' && str_starts_with($path, rtrim($item['url'], '/').'/'))) aria-current="page" @endif @if (! empty($item['new_tab'])) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
            @endforeach
            <a class="btn" href="{{ $install }}">Install on Shopify</a>
        </nav>
    </div>
</header>

<main id="main">
    @yield('content')
</main>

<footer class="site-foot">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <a class="brand" href="/"><span class="brand-mark" aria-hidden="true">C</span><span>{{ $brand['name'] }}</span></a>
                <p class="muted" style="margin-top:14px;max-width:34ch">{{ $brand['tagline'] ?? 'Step-by-step product configurators for Shopify, priced to the cent.' }}</p>
                @if (session('form_sent') === 'newsletter')
                    <p class="sent" style="margin-top:16px;padding:12px">You’re subscribed.</p>
                @else
                    <form class="newsletter" method="post" action="{{ route('site.forms.newsletter') }}">
                        @csrf
                        <input type="hidden" name="source" value="footer">
                        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <label class="hp" for="nl-email">Email</label>
                        <input id="nl-email" type="email" name="email" placeholder="Email for product updates" required>
                        <button type="submit">Subscribe</button>
                    </form>
                @endif
            </div>
            @foreach (['footer_product' => 'Product', 'footer_resources' => 'Resources', 'footer_company' => 'Company', 'footer_legal' => 'Legal'] as $key => $label)
                @if (! empty($menus[$key]))
                    <div>
                        <div class="label">{{ $label }}</div>
                        <ul>
                            @foreach ($menus[$key] as $item)
                                <li><a href="{{ SiteText::url($item['url']) }}" @if (! empty($item['new_tab'])) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="foot-base">
            <span>© {{ date('Y') }} {{ $site['company']['legal_name'] ?? $brand['name'] }}. {{ $site['footer']['note'] ?? 'Composa is not affiliated with Shopify Inc.' }}</span>
            <span class="mono">Billed through Shopify</span>
        </div>
    </div>
</footer>
</body>
</html>
