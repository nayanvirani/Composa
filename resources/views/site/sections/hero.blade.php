@php use App\Support\SiteText; @endphp
<section class="hero">
    <div class="wrap">
        <div class="hero-copy">
            @if (! empty($data['eyebrow']))<div class="label">{{ $data['eyebrow'] }}</div>@endif
            <h1>{{ SiteText::inline($data['title'] ?? '') }}</h1>
            @if (! empty($data['subtitle']))<p class="hero-sub">{{ $data['subtitle'] }}</p>@endif
            <div class="hero-cta">
                @if (! empty($data['primary_label']))<a class="btn" href="{{ SiteText::url($data['primary_url'] ?? $site['links']['install']) }}">{{ $data['primary_label'] }} <span class="arrow">→</span></a>@endif
                @if (! empty($data['secondary_label']))<a class="btn btn--line" href="{{ SiteText::url($data['secondary_url'] ?? '#') }}">{{ $data['secondary_label'] }}</a>@endif
            </div>
            @if (! empty($data['note']))<p class="hero-note">{{ $data['note'] }}</p>@endif
        </div>
        <aside class="title-block" aria-label="Example configured product">
            <div class="tb-head"><span>CONFIGURATION · CFG-0428</span><span>REV B</span></div>
            @if (! empty($data['image']))<img src="{{ $data['image'] }}" alt="" loading="eager">@endif
            <div class="tb-row"><span>Product</span><span>Solitaire ring</span></div>
            <div class="tb-row"><span>Variant</span><span>18k Yellow · Size 7 · 1.5 ct</span></div>
            <div class="tb-row"><span>Options</span><span>Oval cut · Engraving “A &amp; M”</span></div>
            <div class="tb-row"><span>Rules</span><span>3 applied · halo hidden for bezel</span></div>
            <div class="tb-row"><span>Price</span><span class="tb-price">$2,100.50</span></div>
            <div class="tb-row"><span>Checkout</span><span>Signed · verified by Cart Transform</span></div>
        </aside>
    </div>
</section>
