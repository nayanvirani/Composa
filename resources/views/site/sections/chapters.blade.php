@php $chapters = $data['items'] ?? []; @endphp
@if ($data['index'] ?? true)
<section class="sec paper" style="padding: 0 0 56px; margin-top: -1px">
    <div class="wrap">
        <nav aria-label="Feature index" class="idx">
            @foreach ($chapters as $c)
                <a href="#{{ $c['anchor'] ?? \Illuminate\Support\Str::slug($c['title']) }}"><span class="mono">{{ $c['letter'] }}</span><span>{{ $c['title'] }}</span></a>
            @endforeach
        </nav>
    </div>
</section>
@endif
<div class="wrap">
    @foreach ($chapters as $c)
        <section id="{{ $c['anchor'] ?? \Illuminate\Support\Str::slug($c['title']) }}" class="ch">
            <div class="stack" style="gap: 10px"><div class="big">{{ $c['letter'] }}</div><div class="k">Detail {{ $c['letter'] }} · {{ $c['plan'] ?? 'All plans' }}</div></div>
            <div class="stack" style="gap: 14px">
                <h2 class="serif h-4">{{ $c['title'] }}</h2>
                <p class="lead" style="line-height: 1.6">{{ $c['body'] ?? '' }}</p>
                @if (! empty($c['doc_url']))<a href="{{ \App\Support\SiteText::url($c['doc_url']) }}" class="link-u" style="align-self: flex-start">Read the docs →</a>@endif
            </div>
            <div class="spec">
                @foreach (array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($c['spec'] ?? ''))))) as $j => $line)
                    <div><span class="mono">{{ $c['letter'] }}.{{ $j + 1 }}</span><span>{{ $line }}</span></div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
