@extends('site.layout', ['title' => $post->title, 'description' => $post->excerpt, 'seo' => ($post->seo ?? []) + ['image' => $post->cover_image]])

@section('content')
    <article>
        <div class="page-head"><div class="wrap">
            <div class="crumbs"><a href="{{ route('site.journal') }}">Journal</a> / {{ $post->tag ?? 'Post' }}</div>
            <h1>{{ $post->title }}</h1>
            @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
            <p class="label" style="margin-top:20px">{{ $post->author }} · {{ $post->published_at?->format('F j, Y') }} · {{ $post->read_minutes }} min read</p>
        </div></div>
        @if ($post->cover_image)
            <div class="wrap" style="padding-top:40px"><img src="{{ $post->cover_image }}" alt="" style="width:100%;border:1px solid var(--line)"></div>
        @endif
        <div class="wrap" style="padding-top:48px;padding-bottom:72px">
            <div class="prose" style="margin:0 auto">{{ \App\Support\SiteText::document($post->body) }}</div>
        </div>
    </article>
    @if ($related->isNotEmpty())
        <section class="sheet" style="border-top:1px solid var(--line)"><div class="wrap sheet-body">
            <div class="label" style="margin-bottom:24px">Keep reading</div>
            <div class="posts">@foreach ($related as $r)@include('site.partials.post-card', ['post' => $r])@endforeach</div>
        </div></section>
    @endif
@endsection
