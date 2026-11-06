@php
    $components = \App\Models\StatusComponent::query()->orderBy('sort')->get();
    $incidents = \App\Models\StatusIncident::query()->where('started_at', '>=', now()->subDays(90))->orderByDesc('started_at')->get();
    $worst = $components->contains('status', 'outage') ? 'outage' : ($components->contains('status', 'degraded') ? 'degraded' : 'operational');
    $headline = ['operational' => 'All systems running.', 'degraded' => 'Some systems are slow.', 'outage' => 'We’re having an outage.'][$worst];
    $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->startOfDay());
    $badDay = function ($component, $day) use ($incidents) {
        return $incidents->contains(fn ($inc) => in_array($component->id, array_map('intval', $inc->component_ids ?? []), true)
            && $inc->started_at->lte($day->copy()->endOfDay()) && ($inc->resolved_at === null || $inc->resolved_at->gte($day)));
    };
    $dur = function ($inc) {
        if (! $inc->resolved_at) return 'ONGOING';
        $m = $inc->started_at->diffInMinutes($inc->resolved_at);
        return strtoupper($m >= 60 ? intdiv((int) $m, 60).' h '.((int) $m % 60).' min' : (int) $m.' min');
    };
@endphp
<section class="sec" style="padding: 56px 0 40px">
    <div class="wrap stack" style="gap: 24px">
        <span class="k">{{ $data['sheet'] ?? 'Sheet 12 — System status' }}</span>
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap">
            <span style="width: 64px; height: 64px; background: {{ $worst === 'operational' ? '#121212' : '#FF4F00' }}; color: {{ $worst === 'operational' ? '#FFFFFF' : '#121212' }}; display: flex; align-items: center; justify-content: center; font-size: 30px" aria-hidden="true">{{ $worst === 'operational' ? '✓' : '!' }}</span>
            <h1 class="serif" style="font-size: 88px; line-height: .95">{{ $headline }}</h1>
        </div>
        <div class="mono" style="font-size: 12px; color: #5C5C57">UPDATED {{ strtoupper(now()->format('M j, Y H:i')) }} UTC</div>
    </div>
</section>
<section class="sec" style="padding: 32px 0 56px">
    <div class="wrap">
        <div class="comp k" style="padding: 0 0 12px; border-bottom: 1px solid #121212"><span>Component</span><span>Last 30 days</span><span>Now</span></div>
        @foreach ($components as $c)
            @php $bad = $days->filter(fn ($d) => $badDay($c, $d))->count(); @endphp
            <div class="comp">
                <div><div style="font-weight: 600; font-size: 17px">{{ $c->name }}</div><div style="font-size: 13px; color: #5C5C57">{{ $c->description }}</div></div>
                <div class="days" role="img" aria-label="{{ $c->name }}: {{ 30 - $bad }} of 30 days operational">
                    @foreach ($days as $d)<span class="{{ $badDay($c, $d) ? 'bad' : '' }}" title="{{ $d->format('M j') }}: {{ $badDay($c, $d) ? 'incident' : 'operational' }}"></span>@endforeach
                </div>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500"><span class="now-ic" style="background: {{ $c->status === 'operational' ? '#FFFFFF' : '#FF4F00' }}">{{ $c->status === 'operational' ? '✓' : '!' }}</span>{{ ucfirst($c->status) }}</div>
            </div>
        @endforeach
        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 16px; font-size: 13px; color: #5C5C57">
            <span style="display: inline-flex; gap: 6px; align-items: center"><span style="width: 14px; height: 14px; background: #121212"></span>Operational</span>
            <span style="display: inline-flex; gap: 6px; align-items: center"><span style="width: 14px; height: 14px; background: #FF4F00"></span>Incident</span>
        </div>
    </div>
</section>
<section class="sec" style="padding: 56px 0 72px; background: #F5F5F2">
    <div class="wrap stack" style="gap: 20px">
        <h2 class="serif h-4">Incident log</h2>
        @forelse ($incidents as $i)
            <div class="inc">
                <div class="mono"><div style="font-size: 12px">{{ strtoupper($i->started_at->format('M j, Y')) }}</div><div style="font-size: 11px; color: #5C5C57; margin-top: 4px">{{ $dur($i) }}</div></div>
                <div><div style="font-weight: 600">{{ $i->title }}</div><div class="rt" style="font-size: 14px">{{ \App\Support\SiteText::markdown($i->body) }}</div><span class="mono" style="align-self: flex-start; font-size: 11px; border: 1px solid #121212; padding: 2px 6px; background: {{ $i->resolved_at ? '#FFFFFF' : '#FF4F00' }}">{{ $i->resolved_at ? 'RESOLVED' : 'ONGOING' }}</span></div>
            </div>
        @empty
            <p class="lead">No incidents in the last 90 days.</p>
        @endforelse
    </div>
</section>
