<section class="routing" @isset($data['anchor']) id="{{ $data['anchor'] }}" @endisset>
    <div class="wrap stack" style="gap: 40px">
        <div class="sec-head">
            <h2 class="serif h-2" style="max-width: 11em">{{ \App\Support\SiteText::inline($data['title'] ?? '') }}</h2>
            @if (! empty($data['meta']))<span class="mono" style="font-size: 12px; color: #A3A39C">{{ $data['meta'] }}</span>@endif
        </div>
        <div class="flow">
            @foreach ($data['items'] ?? [] as $i => $s)
                <div>
                    <div style="display: flex; align-items: center; gap: 10px"><span class="nb">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><span style="flex: 1; height: 1px; background: #3A3A36"></span><span class="mono" style="color: #6E6E68">→</span></div>
                    <div style="font-size: 22px; font-weight: 500">{{ $s['title'] }}</div>
                    <div style="font-size: 15px; line-height: 1.55; color: #BDBDB6">{{ $s['body'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
