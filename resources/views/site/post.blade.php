@php
    use App\Support\SiteText;
    $no = 'J-'.str_pad($post->journal_no, 2, '0', STR_PAD_LEFT);
@endphp
@extends('site.layout', ['title' => $post->title, 'description' => $post->excerpt, 'seo' => ($post->seo ?? []) + ['image' => $post->cover_image], 'sheetNo' => '05'])

@section('content')
    <header class="sec" style="padding: 56px 0 40px">
        <div class="wrap stack" style="gap: 20px; align-items: center; text-align: center">
            <div class="k"><a href="{{ route('site.journal') }}" style="color: #5C5C57">Journal</a> / {{ $no }} · {{ $post->tag ?? 'Post' }}</div>
            <h1 class="serif h-xl" style="max-width: 11em">{{ SiteText::inline($post->title) }}</h1>
            <div class="mono" style="font-size: 12px; color: #5C5C57">{{ strtoupper($post->author) }} · {{ strtoupper($post->published_at?->format('M j, Y')) }} · {{ $post->read_minutes }} MIN READ</div>
        </div>
    </header>
    @if ($post->cover_image)
        <figure class="sec paper" style="margin: 0; padding: 48px 0; display: flex; flex-direction: column; align-items: center; gap: 12px">
            <img src="{{ $post->cover_image }}" alt="" style="max-width: min(92%, 960px); border: 1px solid #121212">
            <figcaption class="mono" style="font-size: 11px; color: #5C5C57">FIG. {{ $no }}</figcaption>
        </figure>
    @endif
    <article class="wrap post" style="padding-top: 56px; padding-bottom: 72px">
        <div class="gut"></div>
        <div class="body">{{ SiteText::document($post->body) }}</div>
        <aside class="gut"></aside>
    </article>
    @if ($related->isNotEmpty())
        <section style="padding: 56px 0 72px; border-top: 1px solid #121212">
            <div class="wrap stack" style="gap: 20px">
                <div class="k">Related sheets</div>
                <div class="rel">
                    @foreach ($related as $r)
                        <a href="{{ route('site.post', $r->slug) }}"><span class="mono" style="font-size: 12px; color: #C23A00">J-{{ str_pad($r->journal_no, 2, '0', STR_PAD_LEFT) }}</span><span class="serif" style="font-size: 32px; line-height: 1.05">{{ $r->title }}</span><span class="mono" style="margin-top: auto; font-size: 11px; color: #5C5C57">{{ strtoupper($r->tag ?? 'Journal') }} · {{ $r->read_minutes }} MIN</span></a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
