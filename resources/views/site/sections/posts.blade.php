@php $posts = \App\Models\Post::query()->live()->orderByDesc('published_at')->limit((int) ($data['count'] ?? 3))->get(); @endphp
<div class="posts">
    @foreach ($posts as $post)
        @include('site.partials.post-card', ['post' => $post])
    @endforeach
</div>
<p style="margin-top:32px"><a class="btn btn--line" href="{{ route('site.journal') }}">All posts <span class="arrow">→</span></a></p>
