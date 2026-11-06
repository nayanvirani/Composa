<section class="sec" style="padding: 72px 0">
    <div class="wrap">
        <div class="sec-head" style="border-bottom: 1px solid #121212; padding-bottom: 20px">
            <h2 class="serif h-2">{{ \App\Support\SiteText::inline($data['title'] ?? 'Tolerances') }}</h2>
            @if (! empty($data['label']))<span class="k">{{ $data['label'] }}</span>@endif
        </div>
        @foreach ($data['items'] ?? [] as $r)
            <div class="tol">
                <span class="serif" style="font-size: 56px; line-height: 1; color: #FF4F00">{{ $r['code'] }}</span>
                <span style="font-size: 24px; font-weight: 600">{{ $r['title'] }}</span>
                <span style="font-size: 17px; line-height: 1.6; color: #3B3B37">{{ $r['body'] ?? '' }}</span>
            </div>
        @endforeach
    </div>
</section>
