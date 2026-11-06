@php
    use App\Support\SiteText;
    $layout = $data['layout'] ?? 'split';
    $size = ($data['size'] ?? 'xl') === 'l' ? 'h-l' : 'h-xl';
    $buttons = array_filter([
        ! empty($data['primary_label']) ? ['label' => $data['primary_label'], 'url' => $data['primary_url'] ?? $site['links']['install'], 'class' => 'btn'] : null,
        ! empty($data['secondary_label']) ? ['label' => $data['secondary_label'], 'url' => $data['secondary_url'] ?? '#', 'class' => 'btn btn--line'] : null,
    ]);
@endphp
<section class="sec {{ ($data['paper'] ?? true) ? 'paper' : '' }}" style="padding: {{ $layout === 'statement' ? '72px 0' : '56px 0' }}">
    <div class="wrap stack" style="gap: 24px">
        @if ($layout === 'split')
            <div class="sec-head">
                <div class="stack" style="gap: 10px">
                    @if (! empty($data['sheet']))<div class="k">{{ $data['sheet'] }}</div>@endif
                    <h1 class="serif {{ $size }}" style="max-width: 10em">{{ SiteText::inline($data['title'] ?? $page->title) }}</h1>
                </div>
                <div class="stack" style="max-width: 360px; gap: 16px; padding-bottom: 8px">
                    @if (! empty($data['intro']))<p class="lead" style="font-size: 16px">{{ $data['intro'] }}</p>@endif
                    @if ($buttons)
                        <div style="display: flex; gap: 8px; flex-wrap: wrap">
                            @foreach ($buttons as $b)<a class="{{ $b['class'] }}" href="{{ SiteText::url($b['url']) }}">{{ $b['label'] }}</a>@endforeach
                        </div>
                    @endif
                </div>
            </div>
        @elseif ($layout === 'statement')
            @if (! empty($data['sheet']))<div class="k">{{ $data['sheet'] }}</div>@endif
            <p class="serif mbig" style="margin: 0; font-size: 72px; line-height: 1.02; max-width: 15em">{{ SiteText::inline($data['title'] ?? '') }}</p>
            @if (! empty($data['intro']))<p class="lead" style="font-size: 18px; line-height: 1.6; max-width: 40em">{{ $data['intro'] }}</p>@endif
        @else
            @if (! empty($data['sheet']))<div class="k">{{ $data['sheet'] }}</div>@endif
            <h1 class="serif {{ $size }}" style="max-width: 11em">{{ SiteText::inline($data['title'] ?? $page->title) }}</h1>
            @if (! empty($data['intro']))<p class="lead" style="max-width: 40em">{{ $data['intro'] }}</p>@endif
            @if ($buttons)
                <div style="display: flex; gap: 8px; flex-wrap: wrap">
                    @foreach ($buttons as $b)<a class="{{ $b['class'] }}" href="{{ SiteText::url($b['url']) }}">{{ $b['label'] }}</a>@endforeach
                </div>
            @endif
        @endif
    </div>
</section>
