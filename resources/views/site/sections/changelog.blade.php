@php $entries = \App\Models\ChangelogEntry::query()->where('status', 'published')->orderByDesc('released_at')->limit((int) ($data['count'] ?? 30))->get(); @endphp
<div class="wrap">
    @forelse ($entries as $i => $entry)
        <section class="rev" id="v{{ $entry->version }}">
            <div>
                <span class="serif" style="font-size: 56px; line-height: 1; color: {{ $i === 0 ? '#C23A00' : '#121212' }}">Rev {{ $entry->version }}</span>
                <span class="mono" style="font-size: 12px">{{ strtoupper($entry->released_at->format('M j, Y')) }}</span>
                @if ($i === 0)<span class="tag-o">LATEST</span>@endif
            </div>
            <div>
                <h2 class="serif" style="font-size: 36px; line-height: 1.05">{{ $entry->title }}</h2>
                @foreach ($entry->items ?? [] as $item)
                    <div class="it"><span class="mono {{ $item['kind'] }}">{{ $item['kind'] }}</span><span>{{ $item['text'] }}</span></div>
                @endforeach
            </div>
        </section>
    @empty
        <p class="lead" style="padding: 48px 0">No releases yet.</p>
    @endforelse
</div>
