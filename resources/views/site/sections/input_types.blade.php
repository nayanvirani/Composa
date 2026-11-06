<section class="sec" style="padding: 72px 0; background: #F5F5F2">
    <div class="wrap stack" style="gap: 24px">
        <div class="sec-head"><h2 class="serif h-3">{{ \App\Support\SiteText::inline($data['title'] ?? 'Input types, all eleven') }}</h2>@if (! empty($data['label']))<span class="k">{{ $data['label'] }}</span>@endif</div>
        <div class="types">
            @foreach ($data['items'] ?? [] as $i)
                <div><span class="g">{{ $i['glyph'] ?? '•' }}</span><span class="n">{{ $i['name'] }}</span><span class="d">{{ $i['desc'] ?? '' }}</span></div>
            @endforeach
            @if (! empty($data['extra_title']))
                <div style="background: #FF4F00"><span class="g">+</span><span class="n">{{ $data['extra_title'] }}</span><span class="d" style="color: #121212">{{ $data['extra_body'] ?? '' }}</span></div>
            @endif
        </div>
    </div>
</section>
