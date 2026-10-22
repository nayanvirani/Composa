@extends('site.layout', ['title' => $article->title])

@section('content')
    <div class="wrap doc-layout">
        <nav class="doc-nav" aria-label="{{ $article->category?->title }}">
            <a href="{{ route('site.docs') }}" style="border:0;padding-left:0">← All docs</a>
            <div class="label">{{ $article->category?->title }}</div>
            @foreach ($siblings as $s)
                <a href="{{ route('site.doc', $s->slug) }}" @if ($s->id === $article->id) aria-current="page" @endif>{{ $s->title }}</a>
            @endforeach
        </nav>
        <article>
            <div class="crumbs"><a href="{{ route('site.docs') }}">Docs</a> / {{ $article->category?->title }}</div>
            <h1 style="font-size:clamp(32px,4.4vw,52px);margin:16px 0 12px">{{ $article->title }}</h1>
            <p class="label">{{ $article->read_minutes }} min read @if ($article->plan_note) · {{ $article->plan_note }} @endif · Updated {{ $article->updated_at->format('M j, Y') }}</p>
            <div class="prose" style="margin-top:32px">{{ \App\Support\SiteText::document($article->body) }}</div>
            <div class="vote" data-vote="{{ route('site.doc.vote', $article->slug) }}">
                <span>Was this helpful?</span>
                <button type="button" data-helpful="1">Yes</button>
                <button type="button" data-helpful="0">No</button>
                <a href="/contact" class="muted" style="margin-left:auto">Still stuck? Contact support</a>
            </div>
        </article>
    </div>
    <script>
        document.querySelectorAll('[data-vote] button').forEach(function (b) {
            b.addEventListener('click', function () {
                var box = b.closest('[data-vote]');
                fetch(box.dataset.vote, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ helpful: b.dataset.helpful === '1' }) });
                box.innerHTML = '<span>Thanks for the feedback.</span>';
            });
        });
    </script>
@endsection
