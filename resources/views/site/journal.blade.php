@extends('site.layout', ['title' => 'Journal', 'description' => 'Notes on custom products, configurators and selling made-to-order on Shopify.'])

@section('content')
    <div class="page-head"><div class="wrap">
        <div class="crumbs"><a href="/">Composa</a> / Journal</div>
        <h1>The <em>journal</em></h1>
        <p>Guides, merchant stories and product notes.</p>
    </div></div>
    <div class="wrap" style="padding-top:48px;padding-bottom:96px">
        @if ($tags->isNotEmpty())
            <nav class="filters" aria-label="Topics">
                <a href="{{ route('site.journal') }}" aria-current="{{ $tag ? 'false' : 'true' }}"><span class="tag">All</span></a>
                @foreach ($tags as $t)<a href="{{ route('site.journal', ['tag' => $t]) }}" aria-current="{{ $tag === $t ? 'true' : 'false' }}"><span class="tag">{{ $t }}</span></a>@endforeach
            </nav>
        @endif
        @if ($featured)
            <a class="post-featured" href="{{ route('site.post', $featured->slug) }}">
                <div class="cover" style="aspect-ratio:16/10;border:1px solid var(--line);background:var(--paper-2) center/cover @if ($featured->cover_image) url('{{ $featured->cover_image }}') @endif"></div>
                <div style="display:flex;flex-direction:column;gap:16px;justify-content:center">
                    <div class="label">Featured · {{ $featured->tag }}</div>
                    <h2>{{ $featured->title }}</h2>
                    @if ($featured->excerpt)<p class="muted">{{ $featured->excerpt }}</p>@endif
                    <span class="label">{{ $featured->author }} · {{ $featured->read_minutes }} min</span>
                </div>
            </a>
        @endif
        <div class="posts">
            @forelse ($posts as $post)
                @continue($featured && $post->id === $featured->id)
                @include('site.partials.post-card', ['post' => $post])
            @empty
                <p class="muted">No posts yet.</p>
            @endforelse
        </div>
        @if ($posts->hasPages())
            <nav class="pager" aria-label="Pages">
                @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}">← Newer</a>@else<span></span>@endif
                <span class="label">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
                @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}">Older →</a>@else<span></span>@endif
            </nav>
        @endif
    </div>
@endsection
