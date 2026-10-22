@php use App\Support\SiteText; $body = $page->sections[0]['data']['body'] ?? ''; $toc = SiteText::headings($body); @endphp
@extends('site.layout', ['title' => $page->title, 'seo' => $page->seo ?? []])

@section('content')
    <div class="page-head"><div class="wrap">
        <div class="crumbs"><a href="/">Composa</a> / Legal</div>
        <h1>{{ $page->title }}</h1>
        <p class="label" style="margin-top:18px">Last updated {{ $page->updated_at->format('F j, Y') }}</p>
    </div></div>
    <div class="wrap doc-layout">
        <nav class="doc-nav" aria-label="On this page">
            <div class="label">Contents</div>
            @foreach ($toc as $i => [$id, $text])
                <a href="#{{ $id }}">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }} · {{ $text }}</a>
            @endforeach
            @if (! empty($menus['footer_legal']))
                <div class="label">Other policies</div>
                @foreach ($menus['footer_legal'] as $item)<a href="{{ SiteText::url($item['url']) }}" @if ('/'.request()->path() === $item['url']) aria-current="page" @endif>{{ $item['label'] }}</a>@endforeach
            @endif
        </nav>
        <article class="prose">{{ SiteText::document($body) }}</article>
    </div>
@endsection
