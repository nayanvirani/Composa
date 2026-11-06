<section class="sec" style="padding: 72px 0; background: #F5F5F2">
    <div class="wrap stack" style="gap: 24px">
        <h2 class="serif h-3">{{ \App\Support\SiteText::inline($data['title'] ?? 'Drawn for') }}</h2>
        <div class="who">
            @foreach ($data['items'] ?? [] as $w)
                <div><span class="mono" style="font-size: 11px; color: #C23A00">{{ $w['code'] ?? '' }}</span><span class="serif" style="font-size: 30px; line-height: 1">{{ $w['title'] }}</span><span style="font-size: 13px; color: #5C5C57">{{ $w['desc'] ?? '' }}</span></div>
            @endforeach
        </div>
    </div>
</section>
