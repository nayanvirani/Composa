@php
    $placement = $data['placement'] ?? 'home';
    $faqs = \App\Models\Faq::query()->where('status', 'published')->whereJsonContains('placements', $placement)->where('locale', app()->getLocale())->orderBy('sort')->get();
    if ($faqs->isEmpty() && app()->getLocale() !== 'en') {
        $faqs = \App\Models\Faq::query()->where('status', 'published')->whereJsonContains('placements', $placement)->where('locale', 'en')->orderBy('sort')->get();
    }
    $prefix = $data['prefix'] ?? 'N';
@endphp
<section class="sec sec-pad" id="faq">
    <div class="wrap notes">
        <h2 class="serif h-2" style="margin-bottom: 24px; grid-column: 1 / -1">{{ \App\Support\SiteText::inline($data['title'] ?? 'Notes') }}</h2>
        @foreach ($faqs as $i => $faq)
            <details>
                <summary><span class="mono">{{ $prefix }}{{ $i + 1 }}</span>{{ $faq->question }}</summary>
                <div class="ans">{{ \App\Support\SiteText::markdown($faq->answer) }}</div>
            </details>
        @endforeach
    </div>
</section>
