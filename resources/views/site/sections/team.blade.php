<section class="sec" style="padding: 72px 0">
    <div class="wrap stack" style="gap: 24px">
        <div class="sec-head">
            <h2 class="serif h-3">{{ \App\Support\SiteText::inline($data['title'] ?? 'The drafting room') }}</h2>
            @if (! empty($data['link_label']))<a href="{{ \App\Support\SiteText::url($data['link_url'] ?? '/contact') }}" class="link-u">{{ $data['link_label'] }}</a>@endif
        </div>
        <div class="team">
            @foreach ($data['members'] ?? [] as $m)
                <div>
                    <div class="ph paper" @if (! empty($m['photo'])) style="background-image: url('{{ $m['photo'] }}')" @endif>@if (empty($m['photo']))<span class="mono" style="font-size: 12px; color: #5C5C57">{{ strtoupper(mb_substr($m['name'], 0, 1)) }}</span>@endif</div>
                    <div style="padding: 14px 16px"><div style="font-weight: 600">{{ $m['name'] }}</div><div class="mono" style="font-size: 11px; color: #5C5C57; margin-top: 4px">{{ strtoupper($m['role'] ?? '') }}</div></div>
                </div>
            @endforeach
        </div>
    </div>
</section>
