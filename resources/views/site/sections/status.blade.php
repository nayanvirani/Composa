@php
    $components = \App\Models\StatusComponent::query()->orderBy('sort')->get();
    $incidents = \App\Models\StatusIncident::query()->where('started_at', '>=', now()->subDays(90))->orderByDesc('started_at')->get();
    $worst = $components->contains('status', 'outage') ? 'outage' : ($components->contains('status', 'degraded') ? 'degraded' : 'operational');
@endphp
<div class="status-banner" role="status"><span class="dot {{ $worst }}"></span>{{ ['operational' => 'All systems operational', 'degraded' => 'Some systems are degraded', 'outage' => 'We’re having an outage'][$worst] }}</div>
<div class="status-list">
    @foreach ($components as $c)
        <div class="status-row"><span><strong style="font-weight:500">{{ $c->name }}</strong>@if ($c->description)<span class="muted"> — {{ $c->description }}</span>@endif</span><span class="label" style="display:flex;gap:8px;align-items:center"><span class="dot {{ $c->status }}"></span>{{ $c->status }}</span></div>
    @endforeach
</div>
<h3 style="margin-top:48px;font-size:22px">Past 90 days</h3>
@forelse ($incidents as $incident)
    <article class="incident">
        <div class="label">{{ $incident->started_at->format('M j, Y H:i') }} UTC · {{ $incident->severity }} · {{ $incident->resolved_at ? 'Resolved after '.$incident->started_at->diffForHumans($incident->resolved_at, true) : 'Ongoing' }}</div>
        <h4 style="font-size:18px;margin:8px 0">{{ $incident->title }}</h4>
        <div class="prose">{{ \App\Support\SiteText::markdown($incident->body) }}</div>
    </article>
@empty
    <p class="muted" style="margin-top:12px">No incidents.</p>
@endforelse
