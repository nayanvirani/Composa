<section class="sec" style="padding: 96px 0" id="beta">
    <div class="wrap split" style="align-items: start">
        <div class="stack" style="gap: 16px">
            <h2 class="serif h-2">{{ \App\Support\SiteText::inline($data['title'] ?? 'Order form: *beta access*') }}</h2>
            @if (! empty($data['body']))<p class="lead" style="line-height: 1.6; max-width: 28em">{{ $data['body'] }}</p>@endif
        </div>
        @if (session('form_sent') === 'beta')
            <div class="frm"><div class="sent"><span class="tag-o">APPLICATION RECEIVED</span><div class="serif h-4">You’re on the drawing board.</div><p class="lead">We review every application by hand and reply within a week.</p></div></div>
        @else
            <form class="frm" method="post" action="{{ route('site.forms.beta') }}">
                @csrf
                <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="fr"><label for="bi-store">Store</label><input id="bi-store" name="store" value="{{ old('store') }}" placeholder="yourstore.myshopify.com" required></div>
                @error('store')<div class="err">{{ $message }}</div>@enderror
                <div class="fr"><label for="bi-email">Email</label><input id="bi-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@yourstore.com" required></div>
                @error('email')<div class="err">{{ $message }}</div>@enderror
                <div class="fr"><label for="bi-make">You make</label><input id="bi-make" name="current_process" value="{{ old('current_process') }}" placeholder="e.g. custom furniture"></div>
                @error('form')<div class="err">{{ $message }}</div>@enderror
                <button type="submit" class="submit">Submit application <span class="mono">→</span></button>
            </form>
        @endif
    </div>
</section>
