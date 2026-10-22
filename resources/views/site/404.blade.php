@extends('site.layout', ['title' => 'Page not found', 'seo' => ['robots' => 'noindex']])

@section('content')
    <div class="wrap lost">
        <div class="label">Error 404 · Part not found</div>
        <h1>This page is <em>out of stock</em>.</h1>
        <p class="muted" style="max-width:48ch;font-size:18px">The link may be old, or the page has moved. Try the docs or head back to the start.</p>
        <div class="hero-cta"><a class="btn" href="/">Back to home <span class="arrow">→</span></a><a class="btn btn--line" href="{{ route('site.docs') }}">Search the docs</a></div>
    </div>
@endsection
