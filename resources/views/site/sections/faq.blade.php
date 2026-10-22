@php
    $faqs = \App\Models\Faq::query()->where('status', 'published')->whereJsonContains('placements', $data['placement'] ?? 'home')
        ->where('locale', app()->getLocale())->orderBy('sort')->get();
    if ($faqs->isEmpty() && app()->getLocale() !== 'en') {
        $faqs = \App\Models\Faq::query()->where('status', 'published')->whereJsonContains('placements', $data['placement'] ?? 'home')->where('locale', 'en')->orderBy('sort')->get();
    }
@endphp
<div class="faq">
    @foreach ($faqs as $i => $faq)
        <details>
            <summary><span class="label">Q.{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $faq->question }}</span></summary>
            <div class="answer prose">{{ \App\Support\SiteText::markdown($faq->answer) }}</div>
        </details>
    @endforeach
</div>
