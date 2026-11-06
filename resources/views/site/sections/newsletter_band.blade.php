<section class="band band--dark">
    <div class="wrap" style="padding-top: 48px; padding-bottom: 48px">
        <h2 class="serif h-4">{{ \App\Support\SiteText::inline($data['title'] ?? 'New notes, once a month.') }}</h2>
        @if (session('form_sent') === 'newsletter')
            <span class="tag-o" role="status">SUBSCRIBED · THANK YOU</span>
        @else
            <form class="nlb" method="post" action="{{ route('site.forms.newsletter') }}">
                @csrf
                <input type="hidden" name="source" value="{{ $data['source'] ?? 'page' }}">
                <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <label for="nlb-email" class="sr">Email</label>
                <input id="nlb-email" type="email" name="email" placeholder="you@yourstore.com" required>
                <button type="submit">Subscribe</button>
            </form>
        @endif
    </div>
</section>
