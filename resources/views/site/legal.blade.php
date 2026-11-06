@php
    use App\Support\SiteText;
    $body = $page->sections[0]['data']['body'] ?? '';
    $clauses = SiteText::clauses($body);
    $docs = \App\Models\Page::query()->live()->where('template', 'legal')->where('locale', $page->locale)->orderBy('id')->get(['slug', 'title']);
    $docNo = max(1, $docs->search(fn ($d) => $d->slug === $page->slug) + 1);
@endphp
@extends('site.layout', ['title' => $page->title, 'seo' => $page->seo ?? [], 'sheetNo' => '11'])

@section('content')
    <section class="sec" style="padding: 56px 0 0">
        <div class="wrap stack" style="gap: 24px">
            <div class="k">Sheet 11 — Legal</div>
            <h1 class="serif h-l">The fine print, <em>in plain words.</em></h1>
            <nav class="ltabs" aria-label="Documents">
                @foreach ($docs as $d)<a href="/{{ $d->slug }}" @if ($d->slug === $page->slug) aria-current="page" @endif>{{ $d->title }}</a>@endforeach
            </nav>
        </div>
    </section>
    <section style="padding: 40px 0 80px">
        <div class="wrap lg">
            <aside class="stack" style="gap: 14px; position: sticky; top: 120px">
                <div class="tbl" style="display: block">
                    <div style="display: block; padding: 10px 12px; border-bottom: 1px solid #D9D9D3"><div class="k">Document</div><div class="mono" style="font-size: 13px; margin-top: 2px">{{ strtoupper($page->title) }}</div></div>
                    <div style="display: block; padding: 10px 12px; border-bottom: 1px solid #D9D9D3"><div class="k">Revision</div><div class="mono" style="font-size: 13px; margin-top: 2px">{{ $page->updated_at->format('Y.m') }}</div></div>
                    <div style="display: block; padding: 10px 12px"><div class="k">Effective</div><div class="mono" style="font-size: 13px; margin-top: 2px">{{ strtoupper(($page->publish_at ?? $page->created_at)->format('M j, Y')) }}</div></div>
                </div>
                <nav aria-label="Clauses" class="stack">
                    @foreach ($clauses as $i => $c)<a href="#{{ $c['id'] }}" style="font-size: 14px; text-decoration: none; padding: 6px 0; border-bottom: 1px solid #EDEDE8"><span class="mono" style="font-size: 11px; color: #C23A00; margin-right: 8px">{{ $docNo }}.{{ $i + 1 }}</span>{{ $c['title'] }}</a>@endforeach
                </nav>
                <a href="/contact" class="link-u" style="align-self: flex-start">Questions? Contact us →</a>
            </aside>
            <div style="min-width: 0">
                <div class="cl k" style="padding: 0 0 12px; border-bottom: 1px solid #121212"><span>Clause</span><span>{{ $page->title }}</span><span>In plain words</span></div>
                @foreach ($clauses as $i => $c)
                    <div id="{{ $c['id'] }}" class="cl">
                        <span class="serif" style="font-size: 40px; line-height: 1; color: #FF4F00">{{ $docNo }}.{{ $i + 1 }}</span>
                        <div class="stack body" style="gap: 8px"><h2 style="font-size: 20px">{{ $c['title'] }}</h2>{{ $c['html'] }}</div>
                        @if ($c['plain'])<p class="plain">{{ $c['plain'] }}</p>@else<span></span>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
