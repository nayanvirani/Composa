@php
    $templates = \App\Models\Template::query()->where('status', 'published')->where('key', '!=', 'blank')->with('currentVersion')->orderByDesc('featured')->orderBy('sort')->limit((int) ($data['count'] ?? 6))->get();
    $rots = ['-1.5deg', '1deg', '-0.5deg', '1.5deg', '-1deg', '0.8deg'];
@endphp
<section class="sec sec-pad" id="templates">
    <div class="wrap stack" style="gap: 32px">
        <div class="sec-head">
            <h2 class="serif h-2">{{ \App\Support\SiteText::inline($data['title'] ?? 'Start from a pattern') }}</h2>
            <a href="{{ \App\Support\SiteText::url($data['link_url'] ?? '/templates') }}" class="link-u">{{ $data['link_label'] ?? 'All templates →' }}</a>
        </div>
        <div class="tags">
            @foreach ($templates as $i => $t)
                @php $def = $t->currentVersion?->definition ?? []; $pricing = \App\Support\TemplateMeta::pricing($def); @endphp
                <a href="{{ \App\Support\SiteText::url($data['link_url'] ?? '/templates') }}#{{ $t->key }}" class="pin" style="transform: rotate({{ $rots[$i % count($rots)] }})">
                    <span class="no">P-{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="nm">{{ $t->name }}</span>
                    <span class="ds">{{ \Illuminate\Support\Str::limit($t->description, 70) }}</span>
                    <span class="mt">{{ count($def['steps'] ?? []) }} STEPS · {{ strtoupper($pricing) }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
