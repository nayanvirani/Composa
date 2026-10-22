<div class="wrap" style="padding:0">
    <div class="strip" style="border-left:1px solid var(--line);border-right:1px solid var(--line)">
        @foreach ($data['items'] ?? [] as $item)
            <div><strong>{{ $item['value'] }}</strong><span>{{ $item['label'] }}</span></div>
        @endforeach
    </div>
</div>
