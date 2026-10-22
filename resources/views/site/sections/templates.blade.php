@php
    $templates = \App\Models\Template::query()->where('status', 'published')->when($data['featured_only'] ?? false, fn ($q) => $q->where('featured', true))
        ->with('currentVersion')->orderByDesc('featured')->orderBy('sort')->limit((int) ($data['count'] ?? 6))->get();
@endphp
<div class="cards">
    @forelse ($templates as $t)
        @php $def = $t->currentVersion?->definition ?? []; @endphp
        <article class="card">
            <div class="card-img" @if ($t->cover_image) style="background-image:url('{{ $t->cover_image }}')" @endif>@unless ($t->cover_image)<span class="label">{{ $t->category }}</span>@endunless</div>
            <div class="card-body">
                <span class="tag">{{ \App\Filament\Resources\TemplateResource::CATEGORIES[$t->category] ?? $t->category }}</span>
                <h3>{{ $t->name }}</h3>
                @if ($t->description)<p>{{ $t->description }}</p>@endif
                <div class="card-meta label"><span>{{ count($def['steps'] ?? []) }} steps</span><span>{{ count($def['rules'] ?? []) }} rules</span></div>
            </div>
        </article>
    @empty
        <p class="muted">Templates are on their way.</p>
    @endforelse
</div>
