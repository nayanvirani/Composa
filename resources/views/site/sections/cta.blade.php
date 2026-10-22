<section class="cta">
    <div class="wrap">
        <div>
            <h2>{{ \App\Support\SiteText::inline($data['title'] ?? '') }}</h2>
            @if (! empty($data['body']))<p>{{ $data['body'] }}</p>@endif
        </div>
        <a class="btn" href="{{ \App\Support\SiteText::url($data['button_url'] ?? $site['links']['install']) }}">{{ $data['button_label'] ?? 'Install on Shopify' }} <span class="arrow">→</span></a>
    </div>
</section>
