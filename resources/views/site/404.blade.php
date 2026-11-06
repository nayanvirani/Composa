@extends('site.layout', ['title' => 'Page not found', 'seo' => ['robots' => 'noindex'], 'sheetNo' => '—'])

@section('content')
    <section class="sec paper" style="padding: 72px 0 96px">
        <div class="wrap nf">
            <div class="stack" style="gap: 20px">
                <span class="tag-o" style="font-size: 12px">ERROR 404 · PART NOT FOUND</span>
                <h1 class="serif h-xl">This part isn’t on <em>the drawing.</em></h1>
                <p class="lead" style="line-height: 1.6; max-width: 30em">The page you’re looking for has moved or never existed. Try one of these instead.</p>
                <div class="list-links">
                    @foreach ([['01', 'Home', '/'], ['02', 'Pattern library', '/templates'], ['04', 'Docs', '/docs'], ['09', 'Contact us', '/contact']] as [$n, $t, $u])
                        <a href="{{ $u }}"><span><span class="mono">{{ $n }}</span>{{ $t }}</span><span class="mono">→</span></a>
                    @endforeach
                </div>
            </div>
            <svg width="460" height="360" viewBox="0 0 460 360" role="img" aria-label="Drawing of an empty part outline with a missing-part callout" style="height: auto">
                <g fill="none" stroke="#121212" stroke-width="1.6"><rect x="60" y="60" width="300" height="220" stroke-dasharray="8 6"></rect><path d="M60 310 L360 310 M60 302 L60 318 M360 302 L360 318"></path><path d="M390 60 L390 280 M382 60 L398 60 M382 280 L398 280"></path><path d="M210 170 L300 110"></path></g>
                <circle cx="210" cy="170" r="14" fill="#FF4F00"></circle>
                <text x="210" y="175" text-anchor="middle" font-family="Geist Mono, monospace" font-size="13" fill="#121212">?</text>
                <text x="306" y="104" font-family="Geist Mono, monospace" font-size="12" fill="#121212">MISSING</text>
                <text x="190" y="334" font-family="Geist Mono, monospace" font-size="12" fill="#5C5C57">W = ?</text>
                <text x="404" y="176" font-family="Geist Mono, monospace" font-size="12" fill="#5C5C57">H = ?</text>
            </svg>
        </div>
    </section>
@endsection
