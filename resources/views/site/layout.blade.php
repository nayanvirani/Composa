@php
    use App\Support\SiteText;
    $brand = $site['brand'];
    $suffix = $site['seo']['title_suffix'] ?? ' — '.$brand['name'];
    $metaTitle = ($seo['title'] ?? null) ?: (($title ?? null) ? $title.$suffix : $brand['name'].' — '.($brand['tagline'] ?? 'Product configurator for Shopify'));
    $metaDescription = ($seo['description'] ?? null) ?: ($description ?? null) ?: ($site['seo']['description'] ?? null);
    $metaImage = ($seo['image'] ?? null) ?: ($site['seo']['image'] ?? null);
    $install = $site['links']['install'];
    $path = '/'.trim(request()->path(), '/');
    $path = $path === '/' ? '/' : rtrim($path, '/');
    $isCurrent = fn (string $url) => $url === '/' ? $path === '/' : ($path === rtrim($url, '/') || str_starts_with($path, rtrim($url, '/').'/'));

    // Sheet numbers follow the design: every page is one sheet of the product drawing set.
    $sheets = ['/' => '01', '/templates' => '02', '/pricing' => '03', '/docs' => '04', '/journal' => '05', '/features' => '06', '/changelog' => '07', '/about' => '08', '/contact' => '09', '/early-access' => '10', '/legal' => '11', '/status' => '12'];
    $minimal = $minimal ?? false;
    $sheet = $sheetNo ?? collect($sheets)->first(fn ($n, $url) => $isCurrent($url)) ?? '—';

    $header = $menus['header'] ?? [['label' => 'Product', 'url' => '/'], ['label' => 'Templates', 'url' => '/templates'], ['label' => 'Pricing', 'url' => '/pricing'], ['label' => 'Docs', 'url' => '/docs'], ['label' => 'Journal', 'url' => '/journal']];
    $mobile = collect($header)->concat($menus['footer_product'] ?? [])->concat($menus['footer_company'] ?? [])->unique('url')->values();
    $footerCols = ['footer_product' => 'Product', 'footer_resources' => 'Resources', 'footer_company' => 'Company', 'footer_legal' => 'Legal'];
    $meta = $site['footer']['meta'] ?? [['k' => 'Rev', 'v' => now()->format('Y.m')], ['k' => 'Status', 'v' => 'Beta'], ['k' => 'Scale', 'v' => 'Any product'], ['k' => 'Locale', 'v' => 'English · USD'], ['k' => 'Built on', 'v' => 'Shopify'], ['k' => '©', 'v' => ($site['company']['legal_name'] ?? $brand['name']).' '.date('Y')]];
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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
@vite(['resources/css/site.css'])
<script>document.documentElement.classList.add('js')</script>
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

@if (! empty($sitePreview))
<div class="notice" role="region" aria-label="Preview" style="background: #121212; color: #FFFFFF">
    <div class="wrap"><span class="mono" style="font-size: 12px">STAFF PREVIEW — visitors still see the coming-soon page.</span><a href="?preview=off" style="color: #FF6A2A; font-weight: 600">Exit preview →</a></div>
</div>
@endif
@if ($announcement)
<div class="notice" role="region" aria-label="Announcement">
    <div class="wrap">
        <span>{{ $announcement->message }}</span>
        @if ($announcement->link_url)<a href="{{ SiteText::url($announcement->link_url) }}" style="font-weight:600">{{ $announcement->link_label ?: 'Read more' }} →</a>@endif
    </div>
</div>
@endif

<header class="hd">
    <div class="hd-strip">
        <div class="wrap">
            <span>Sheet {{ $sheet }} / 12<span class="strip-long"> — {{ $brand['name'] }} · {{ $site['brand']['strip'] ?? 'product configurator for Shopify' }}</span></span>
            @unless ($minimal)<a href="{{ SiteText::url($site['links']['strip_url'] ?? '/early-access') }}">{{ $site['links']['strip_label'] ?? 'Beta open → apply' }}</a>@endunless
        </div>
    </div>
    <div class="wrap hd-row">
        <a href="/" class="logo" aria-label="{{ $brand['name'] }} home">
            @if (! empty($brand['logo_url']))
                <img src="{{ $brand['logo_url'] }}" alt="">
            @else
                <svg width="28" height="28" viewBox="0 0 30 30" aria-hidden="true"><rect width="30" height="30" fill="#FF4F00"></rect><path d="M6 24 A18 18 0 0 1 24 6 L24 24 Z" fill="#121212"></path></svg>
            @endif
            <span>{{ $brand['name'] }}</span>
        </a>
        @unless ($minimal)
        <nav aria-label="Main" class="nav-d">
            @foreach ($header as $i => $item)
                <a href="{{ SiteText::url($item['url']) }}" @if ($isCurrent($item['url'])) aria-current="page" @endif @if (! empty($item['new_tab'])) target="_blank" rel="noopener" @endif><span class="n">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>{{ $item['label'] }}</a>
            @endforeach
        </nav>
        <div class="hd-actions">
            <a href="{{ $site['links']['login'] ?? 'https://admin.shopify.com' }}" class="login" rel="noopener">Log in</a>
            <a href="{{ $install }}" class="install">Install <span>→</span></a>
            <button type="button" class="menu-btn" aria-expanded="false" aria-controls="nav-m" aria-label="Open menu" data-menu>
                <svg class="i-open" width="18" height="14" viewBox="0 0 18 14" aria-hidden="true"><path d="M0 1h18M0 7h18M0 13h18" stroke="#121212" stroke-width="1.6"></path></svg>
                <svg class="i-close" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 2l12 12M14 2L2 14" stroke="#121212" stroke-width="1.6"></path></svg>
            </button>
        </div>
        @endunless
    </div>
    @unless ($minimal)
    <nav id="nav-m" class="nav-m paper" aria-label="Mobile menu">
        @foreach ($mobile as $item)
            @php $n = $sheets[rtrim($item['url'], '/') ?: '/'] ?? str_pad($loop->iteration, 2, '0', STR_PAD_LEFT); @endphp
            <a class="ml" href="{{ SiteText::url($item['url']) }}" @if ($isCurrent($item['url'])) aria-current="page" @endif><span class="n">{{ $n }}</span><span class="t">{{ $item['label'] }}</span></a>
        @endforeach
        <div class="m-cta">
            <a href="{{ $site['links']['login'] ?? 'https://admin.shopify.com' }}">Log in</a>
            <a href="{{ $install }}">Install on Shopify →</a>
        </div>
    </nav>
    @endunless
</header>

<main id="main">
    @yield('content')
</main>

<footer class="ft">
    <div class="wrap">
        <div class="wm" aria-hidden="true">{{ $brand['name'] }}<span>.</span></div>
        <div class="tblock">
            <div class="{{ $minimal ? 'span5' : 'span2' }}">
                <span class="k">Drawn for</span>
                <span style="font-size: 15px; line-height: 1.5">{{ $site['footer']['drawn_for'] ?? 'Made-to-order and customizable products on any Shopify store — jewelry, furniture, print, apparel, gifts, B2B.' }}</span>
                @if (session('form_sent') === 'newsletter')
                    <span class="tag-o" role="status" style="margin-top: 6px">SUBSCRIBED · THANK YOU</span>
                @else
                    <form class="nl" method="post" action="{{ route('site.forms.newsletter') }}">
                        @csrf
                        <input type="hidden" name="source" value="footer">
                        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <label for="nl-email" class="sr">Email for product updates</label>
                        <input id="nl-email" type="email" name="email" placeholder="Updates by email" required>
                        <button type="submit">Subscribe</button>
                    </form>
                @endif
            </div>
            @foreach ($minimal ? ['footer_legal' => 'Legal'] : $footerCols as $key => $label)
                <div>
                    <span class="k">{{ $label }}</span>
                    @foreach ($menus[$key] ?? [] as $item)
                        <a class="fa" href="{{ SiteText::url($item['url']) }}" @if (! empty($item['new_tab'])) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
                    @endforeach
                </div>
            @endforeach
            @foreach ($meta as $m)
                <div class="meta"><span class="k">{{ $m['k'] }}</span><span class="v">{{ $m['v'] }}</span></div>
            @endforeach
        </div>
        @if (! empty($site['footer']['note']))<p class="k" style="margin:0">{{ $site['footer']['note'] }}</p>@endif
    </div>
</footer>
<script>
(function () {
  var b = document.querySelector('[data-menu]'), m = document.getElementById('nav-m');
  if (b && m) b.addEventListener('click', function () { var o = m.classList.toggle('is-open'); b.setAttribute('aria-expanded', o); b.setAttribute('aria-label', o ? 'Close menu' : 'Open menu'); });
})();
</script>
@stack('scripts')
</body>
</html>
