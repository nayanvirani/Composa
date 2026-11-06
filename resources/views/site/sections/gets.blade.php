<section class="sec" style="padding: 64px 0">
    <div class="wrap stack" style="gap: 24px">
        <h2 class="serif h-3">{{ \App\Support\SiteText::inline($data['title'] ?? 'What beta stores get') }}</h2>
        <div class="gets">
            @foreach ($data['items'] ?? [] as $g)
                <div><span class="serif" style="font-size: 56px; line-height: 1; color: #FF4F00">{{ $g['code'] ?? '' }}</span><span style="font-size: 20px; font-weight: 600">{{ $g['title'] }}</span><span style="font-size: 15px; color: #3B3B37; line-height: 1.55">{{ $g['body'] ?? '' }}</span></div>
            @endforeach
        </div>
    </div>
</section>
