@php
    use App\Support\SiteText;
    $toc = SiteText::headings($article->body);
    $pos = $siblings->search(fn ($s) => $s->id === $article->id);
    $prev = $pos !== false && $pos > 0 ? $siblings[$pos - 1] : null;
    $next = $pos !== false && $pos < $siblings->count() - 1 ? $siblings[$pos + 1] : null;
    $catNo = $article->category ? \App\Models\DocCategory::query()->where('sort', '<', $article->category->sort)->count() + 1 : 0;
@endphp
@extends('site.layout', ['title' => $article->title, 'sheetNo' => '04'])

@section('content')
    <div class="wrap doc" style="padding-top: 40px; padding-bottom: 80px">
        <nav aria-label="{{ $article->category?->title }}" class="doc-side">
            <div class="k" style="padding: 12px 14px; border-bottom: 1px solid #121212">{{ str_pad($catNo, 2, '0', STR_PAD_LEFT) }} · {{ $article->category?->title }}</div>
            @foreach ($siblings as $s)
                <a class="s" href="{{ route('site.doc', $s->slug) }}" @if ($s->id === $article->id) aria-current="page" @endif>{{ $s->title }}</a>
            @endforeach
            <a href="{{ route('site.docs') }}" class="mono" style="display: block; padding: 12px 14px; font-size: 12px; text-decoration: none">← ALL DOCS</a>
        </nav>
        <article class="art" style="min-width: 0">
            <div class="k"><a href="{{ route('site.docs') }}" style="color: #5C5C57">Docs</a> / {{ $article->category?->title }} / {{ $catNo }}.{{ ($pos !== false ? $pos : 0) + 1 }}</div>
            <h1 class="serif" style="margin: 12px 0 8px; font-size: 72px; line-height: .95">{{ $article->title }}</h1>
            <div class="metaline"><span>{{ $article->read_minutes }} MIN READ</span><span>{{ strtoupper($article->plan_note ?: 'All plans') }}</span><span>UPDATED {{ strtoupper($article->updated_at->format('M j, Y')) }}</span></div>
            {{ SiteText::document($article->body) }}
            <div class="vote" data-vote="{{ route('site.doc.vote', $article->slug) }}">
                <span style="font-size: 15px">Was this helpful?</span>
                <div style="display: flex; gap: 8px"><button type="button" data-helpful="1">Yes</button><button type="button" data-helpful="0">No</button></div>
            </div>
            @if ($prev || $next)
                <div class="pn">
                    @if ($prev)<a href="{{ route('site.doc', $prev->slug) }}"><span class="k">← Previous</span><div style="font-weight: 600; margin-top: 4px">{{ $prev->title }}</div></a>@else<span></span>@endif
                    @if ($next)<a href="{{ route('site.doc', $next->slug) }}" style="text-align: right"><span class="k">Next →</span><div style="font-weight: 600; margin-top: 4px">{{ $next->title }}</div></a>@endif
                </div>
            @endif
        </article>
        <aside class="toc">
            @if ($toc)
                <span class="k" style="margin-bottom: 6px">On this sheet</span>
                @foreach ($toc as [$id, $text])<a href="#{{ $id }}">{{ $text }}</a>@endforeach
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-vote] button').forEach(function (b) {
  b.addEventListener('click', function () {
    var box = b.closest('[data-vote]');
    fetch(box.dataset.vote, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ helpful: b.dataset.helpful === '1' }) });
    box.innerHTML = '<span style="font-size:15px">Thanks — noted on the sheet.</span>';
  });
});
</script>
@endpush
