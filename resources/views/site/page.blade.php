@extends('site.layout', ['title' => $page->slug === 'home' ? null : $page->title, 'seo' => $page->seo ?? []])

@section('content')
    @foreach ($page->visibleSections() as $section)
        @if (view()->exists('site.sections.'.$section['type']))
            @include('site.sections.'.$section['type'], ['data' => $section['data'] ?? [], 'page' => $page])
        @endif
    @endforeach
@endsection
