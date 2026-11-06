@php $dark = ($data['style'] ?? 'orange') === 'dark'; @endphp
<section class="band {{ $dark ? 'band--dark' : 'band--orange' }}">
    <div class="wrap">
        <h2 class="serif" style="font-size: {{ ($data['size'] ?? 'l') === 'xl' ? '72px' : '56px' }}; line-height: .95; max-width: 16em">{{ \App\Support\SiteText::inline($data['title'] ?? '') }}</h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap">
            @if (! empty($data['secondary_label']))<a class="btn btn--line" href="{{ \App\Support\SiteText::url($data['secondary_url'] ?? '#') }}">{{ $data['secondary_label'] }}</a>@endif
            <a class="btn {{ $dark ? 'btn--orange' : '' }}" style="min-height: 56px" href="{{ \App\Support\SiteText::url($data['button_url'] ?? $site['links']['install']) }}">{{ $data['button_label'] ?? 'Install on Shopify' }} <span class="arr">→</span></a>
        </div>
    </div>
</section>
