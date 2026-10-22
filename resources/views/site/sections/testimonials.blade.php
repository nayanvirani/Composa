@php $quotes = \App\Models\Testimonial::query()->where('status', 'published')->orderBy('sort')->limit((int) ($data['count'] ?? 3))->get(); @endphp
<div class="quotes">
    @foreach ($quotes as $q)
        <figure class="quote" style="margin:0">
            <blockquote>“{{ $q->quote }}”</blockquote>
            <figcaption><strong>{{ $q->name }}</strong>@if ($q->store)<span class="muted"> · {{ $q->store }}</span>@endif</figcaption>
        </figure>
    @endforeach
</div>
