@extends('site.layout', ['title' => 'Docs', 'description' => 'Guides for building, pricing and launching product configurators with Composa.'])

@section('content')
    <div class="page-head"><div class="wrap">
        <div class="crumbs"><a href="/">Composa</a> / Docs</div>
        <h1>Help <em>centre</em></h1>
        <p>Set-up guides, variant mapping, pricing rules and checkout — written for merchants, not developers.</p>
        <form class="search" action="{{ route('site.docs') }}" role="search">
            <label class="hp" for="doc-q">Search the docs</label>
            <input id="doc-q" type="search" name="q" value="{{ $q }}" placeholder="Search, e.g. “price table” or “sold out”">
            <button type="submit">Search</button>
        </form>
    </div></div>
    <div class="wrap" style="padding-top:48px;padding-bottom:96px">
        @if ($results !== null)
            <div class="label" style="margin-bottom:16px">{{ $results->count() }} result{{ $results->count() === 1 ? '' : 's' }} for “{{ $q }}”</div>
            <div class="log" style="margin-bottom:56px">
                @forelse ($results as $a)
                    <div class="status-row"><a href="{{ route('site.doc', $a->slug) }}">{{ $a->title }}</a><span class="label">{{ $a->category?->title }}</span></div>
                @empty
                    <p class="muted" style="padding:16px 0">Nothing found. Try another word, or <a href="/contact">ask us</a>.</p>
                @endforelse
            </div>
        @endif
        @if ($startHere->isNotEmpty() && $results === null)
            <div class="label" style="margin-bottom:16px">Start here</div>
            <div class="grid" style="margin-bottom:56px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
                @foreach ($startHere as $i => $a)
                    <a class="cell" href="{{ route('site.doc', $a->slug) }}" style="text-decoration:none">
                        <span class="code">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <h3>{{ $a->title }}</h3>
                        <p class="label">{{ $a->read_minutes }} min read</p>
                    </a>
                @endforeach
            </div>
        @endif
        <div class="doc-cats">
            @foreach ($categories as $cat)
                <section class="doc-cat">
                    <h2>{{ $cat->title }}</h2>
                    @if ($cat->description)<p class="muted" style="font-size:14px">{{ $cat->description }}</p>@endif
                    <ul>@foreach ($cat->articles as $a)<li><a href="{{ route('site.doc', $a->slug) }}">{{ $a->title }}</a></li>@endforeach</ul>
                </section>
            @endforeach
        </div>
    </div>
@endsection
