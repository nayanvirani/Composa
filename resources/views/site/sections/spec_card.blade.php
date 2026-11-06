<section class="sec" style="padding: 96px 0; background: #F5F5F2">
    <div class="wrap split">
        <div class="card-ink" style="transform: rotate(-1.2deg)">
            <div style="display: flex; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #121212; gap: 12px"><span class="mono" style="font-size: 12px">{{ $data['card_title'] ?? 'PRODUCTION SPEC · ORDER #1042' }}</span>@if (! empty($data['badge']))<span class="mono" style="font-size: 12px; background: #FF4F00; padding: 2px 8px">{{ $data['badge'] }}</span>@endif</div>
            @foreach ($data['rows'] ?? [] as $r)
                <div class="kv"><span>{{ $r['k'] }}</span><span>{{ $r['v'] }}</span></div>
            @endforeach
            <div class="mono" style="padding: 14px 18px; font-size: 12px; display: flex; justify-content: space-between"><span>{{ $data['footer_left'] ?? 'Checked by ______' }}</span><span>{{ $data['footer_right'] ?? 'Download PDF ↓' }}</span></div>
        </div>
        <div class="stack" style="gap: 18px">
            @if (! empty($data['eyebrow']))<div class="k" style="color: #C23A00">{{ $data['eyebrow'] }}</div>@endif
            <h2 class="serif h-3">{{ \App\Support\SiteText::inline($data['title'] ?? '') }}</h2>
            @if (! empty($data['body']))<p class="lead" style="line-height: 1.6">{{ $data['body'] }}</p>@endif
        </div>
    </div>
</section>
