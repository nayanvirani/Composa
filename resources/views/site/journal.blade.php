@extends('site.layout', ['title' => 'Journal', 'description' => 'Guides and stories on selling made-to-order products on Shopify.'])

@php $no = fn ($post) => 'J-'.str_pad($post->journal_no, 2, '0', STR_PAD_LEFT); @endphp

@section('content')
    <section class="sec" style="padding: 56px 0 40px">
        <div class="wrap stack" style="gap: 28px">
            <div class="sec-head">
                <div><div class="k">Sheet 05 — Journal</div><h1 class="serif h-xl" style="margin-top: 10px">Field <em>notes.</em></h1></div>
                <p class="lead" style="max-width: 360px; font-size: 16px">Guides and stories on selling made-to-order products — pricing, production and the craft of a good configurator.</p>
            </div>
            @if ($featured)
                <a href="{{ route('site.post', $featured->slug) }}" class="feat">
                    <div class="fig {{ $featured->cover_image ? '' : 'paper' }}" @if ($featured->cover_image) style="background-image: url('{{ $featured->cover_image }}')" @endif>
                        @unless ($featured->cover_image)
                            <svg width="300" height="220" viewBox="0 0 300 220" aria-hidden="true"><g fill="none" stroke="#121212" stroke-width="1.6"><rect x="30" y="40" width="80" height="140"></rect><rect x="120" y="40" width="80" height="140" stroke="#FF4F00" stroke-width="2.4"></rect><rect x="210" y="40" width="60" height="140" stroke-dasharray="5 5"></rect><path d="M30 200 L270 200 M30 194 L30 206 M270 194 L270 206"></path></g></svg>
                        @endunless
                        <span class="mono" style="position: absolute; left: 14px; top: 12px; font-size: 11px; background: #fff; padding: 2px 4px">FIG. {{ $no($featured) }}</span>
                    </div>
                    <div class="stack" style="padding: 28px; gap: 14px">
                        <span class="tag-o">FEATURED · {{ strtoupper($featured->tag ?? 'Journal') }}</span>
                        <h2 class="serif" style="font-size: 52px; line-height: 1">{{ $featured->title }}</h2>
                        @if ($featured->excerpt)<p class="lead" style="font-size: 16px; line-height: 1.6">{{ $featured->excerpt }}</p>@endif
                        <div class="mono" style="margin-top: auto; font-size: 12px; color: #5C5C57">{{ strtoupper($featured->author) }} · {{ strtoupper($featured->published_at?->format('M j, Y')) }} · {{ $featured->read_minutes }} MIN</div>
                    </div>
                </a>
            @endif
        </div>
    </section>

    <section class="sec" style="padding: 40px 0 72px">
        <div class="wrap stack" style="gap: 16px">
            @if ($tags->isNotEmpty())
                <nav class="seg" aria-label="Topic">
                    <a href="{{ route('site.journal') }}" aria-current="{{ $tag ? 'false' : 'true' }}">All</a>
                    @foreach ($tags as $t)<a href="{{ route('site.journal', ['tag' => $t]) }}" aria-current="{{ $tag === $t ? 'true' : 'false' }}">{{ $t }}</a>@endforeach
                </nav>
            @endif
            <div class="jrow k" style="padding: 14px 0; border-bottom: 1px solid #121212"><span>No.</span><span>Title</span><span class="hide">Topic</span><span class="hide">Date</span><span class="hide">Read</span></div>
            @forelse ($posts as $post)
                <a href="{{ route('site.post', $post->slug) }}" class="jrow">
                    <span class="mono" style="font-size: 13px; color: #C23A00">{{ $no($post) }}</span>
                    <span class="stack" style="gap: 4px"><span class="serif">{{ $post->title }}</span>@if ($post->excerpt)<span style="font-size: 14px; color: #5C5C57">{{ $post->excerpt }}</span>@endif</span>
                    <span class="hide mono" style="font-size: 12px">{{ strtoupper($post->tag ?? '—') }}</span>
                    <span class="hide mono" style="font-size: 12px; color: #5C5C57">{{ strtoupper($post->published_at?->format('M j, Y')) }}</span>
                    <span class="hide mono" style="font-size: 12px; color: #5C5C57">{{ $post->read_minutes }} MIN</span>
                </a>
            @empty
                <p class="lead" style="padding: 24px 0">No posts yet.</p>
            @endforelse
            @if ($posts->hasPages())
                <nav class="pager" aria-label="Pages">
                    @if ($posts->previousPageUrl())<a class="btn btn--line" href="{{ $posts->previousPageUrl() }}">← Newer</a>@else<span></span>@endif
                    <span class="k">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
                    @if ($posts->nextPageUrl())<a class="btn btn--line" href="{{ $posts->nextPageUrl() }}">Older →</a>@else<span></span>@endif
                </nav>
            @endif
        </div>
    </section>

    @include('site.sections.newsletter_band', ['data' => ['title' => 'New notes, once a month.', 'source' => 'journal']])
@endsection
