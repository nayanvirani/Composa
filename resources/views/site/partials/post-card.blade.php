<a class="post-card" href="{{ route('site.post', $post->slug) }}">
    <div class="cover" @if ($post->cover_image) style="background-image:url('{{ $post->cover_image }}')" @endif></div>
    <div class="label">{{ $post->tag ?? 'Journal' }} · {{ $post->published_at?->format('M j, Y') }} · {{ $post->read_minutes }} min</div>
    <h3>{{ $post->title }}</h3>
    @if ($post->excerpt)<p class="muted">{{ $post->excerpt }}</p>@endif
</a>
