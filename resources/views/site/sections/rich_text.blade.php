<section class="sec sec-pad">
    <div class="wrap" style="max-width: 860px">
        @if (! empty($data['title']))<h2 class="serif h-3" style="margin-bottom: 24px">{{ \App\Support\SiteText::inline($data['title']) }}</h2>@endif
        <div class="rt">{{ \App\Support\SiteText::markdown($data['body'] ?? '') }}</div>
    </div>
</section>
