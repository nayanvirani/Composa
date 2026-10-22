@extends('site.layout', ['title' => $page->slug === 'home' ? null : $page->title, 'seo' => $page->seo ?? []])

@section('content')
    @php
        $sections = $page->visibleSections();
        $framed = array_values(array_filter($sections, fn ($s) => ! in_array($s['type'], ['hero', 'spec_strip', 'cta'], true)));
        $n = 0;
    @endphp
    @if (($sections[0]['type'] ?? null) !== 'hero')
        <div class="page-head"><div class="wrap">
            <div class="crumbs"><a href="/">Composa</a> / {{ $page->title }}</div>
            <h1>{{ $page->title }}</h1>
            @if (! empty($page->seo['description']))<p>{{ $page->seo['description'] }}</p>@endif
        </div></div>
    @endif
    @foreach ($sections as $section)
        @php $data = $section['data'] ?? []; @endphp
        @if (in_array($section['type'], ['hero', 'spec_strip', 'cta'], true))
            @include('site.sections.'.$section['type'], ['data' => $data, 'page' => $page])
        @elseif (view()->exists('site.sections.'.$section['type']))
            @php $n++; @endphp
            @component('site.partials.sheet', ['n' => $n, 'total' => count($framed), 'eyebrow' => $data['eyebrow'] ?? null, 'kind' => \App\Support\PageSections::label($section['type']), 'title' => $data['title'] ?? null, 'intro' => $data['intro'] ?? null])
                @include('site.sections.'.$section['type'], ['data' => $data, 'page' => $page])
            @endcomponent
        @endif
    @endforeach
@endsection
