<div class="grid">
    @foreach ($data['items'] ?? [] as $i => $item)
        <article class="cell">
            <span class="code">{{ $item['code'] ?? 'F-'.str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
            <h3>{{ $item['title'] }}</h3>
            @if (! empty($item['body']))<p>{{ $item['body'] }}</p>@endif
        </article>
    @endforeach
</div>
