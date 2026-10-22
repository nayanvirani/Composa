<ol class="steps" style="list-style:none;padding:0;margin:0">
    @foreach ($data['items'] ?? [] as $item)
        <li class="step"><h3>{{ $item['title'] }}</h3>@if (! empty($item['body']))<p>{{ $item['body'] }}</p>@endif</li>
    @endforeach
</ol>
