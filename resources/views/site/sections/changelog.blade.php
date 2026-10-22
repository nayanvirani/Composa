@php $entries = \App\Models\ChangelogEntry::query()->where('status', 'published')->orderByDesc('released_at')->limit((int) ($data['count'] ?? 10))->get(); @endphp
<div class="log">
    @foreach ($entries as $entry)
        <article class="log-entry" id="v{{ $entry->version }}">
            <div><div class="mono">v{{ $entry->version }}</div><div class="label" style="margin-top:6px">{{ $entry->released_at->format('M j, Y') }}</div></div>
            <div>
                <h3>{{ $entry->title }}</h3>
                <ul>
                    @foreach ($entry->items ?? [] as $item)
                        <li><span class="mono kind-{{ $item['kind'] }}">{{ ucfirst($item['kind']) }}</span><span>{{ $item['text'] }}</span></li>
                    @endforeach
                </ul>
            </div>
        </article>
    @endforeach
</div>
