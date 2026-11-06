<section class="sec paper" style="padding: 96px 0">
    <div class="wrap split">
        <div class="stack" style="gap: 20px">
            @if (! empty($data['eyebrow']))<div class="k" style="color: #C23A00">{{ $data['eyebrow'] }}</div>@endif
            <h2 class="serif h-3">{{ \App\Support\SiteText::inline($data['title'] ?? '') }}</h2>
            @if (! empty($data['body']))<p class="lead" style="line-height: 1.6; max-width: 30em">{{ $data['body'] }}</p>@endif
        </div>
        <div style="background: #FFFFFF; border: 1px solid #121212; padding: 36px 32px; position: relative">
            <div class="serif mformula" style="font-size: 44px; line-height: 1.25; font-style: italic">{{ $data['formula'] ?? 'P = (B + Σo) × (1 + m) + f(x)' }}</div>
            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; margin-top: 28px">
                @foreach ($data['terms'] ?? [] as $t)
                    <div style="display: flex; gap: 10px; align-items: baseline; border-top: 1px solid #D9D9D3; padding-top: 10px"><span class="serif" style="font-size: 24px; font-style: italic; color: #C23A00; min-width: 28px">{{ $t['symbol'] }}</span><span style="font-size: 14px; color: #3B3B37">{{ $t['text'] }}</span></div>
                @endforeach
            </div>
            @if (! empty($data['badge']))<div class="mono" style="position: absolute; top: -11px; right: 20px; background: #FF4F00; color: #121212; font-size: 11px; padding: 3px 8px">{{ $data['badge'] }}</div>@endif
        </div>
    </div>
</section>
