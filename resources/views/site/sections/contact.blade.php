@php
    use App\Support\SiteText;
    $topics = [
        'support' => ['Support', 'REPLY WITHIN 1 BUSINESS DAY', 'Configurator', 'Which configurator is affected?', 'What happened, and what did you expect?'],
        'sales' => ['Sales & setup', 'REPLY WITHIN 1 BUSINESS DAY', 'Products', 'What do you make? e.g. custom sofas', 'Tell us about your products and options.'],
        'partners' => ['Partners', 'REPLY WITHIN 3 BUSINESS DAYS', 'Agency', 'Agency name and website', 'How many stores do you build for?'],
        'press' => ['Press', 'REPLY WITHIN 3 BUSINESS DAYS', 'Publication', 'Where will this appear?', 'What are you writing about?'],
    ];
    $current = old('topic', request('topic', 'support'));
    $current = isset($topics[$current]) ? $current : 'support';
    $channels = $data['channels'] ?? [];
@endphp
<section class="sec paper" style="padding: 56px 0 80px">
    <div class="wrap ct">
        <div class="stack" style="gap: 24px">
            @if (! empty($data['sheet']))<div class="k">{{ $data['sheet'] }}</div>@endif
            <h1 class="serif h-l">{{ SiteText::inline($data['title'] ?? 'Send us a *request.*') }}</h1>
            @if (! empty($data['intro']))<p class="lead" style="line-height: 1.6">{{ $data['intro'] }}</p>@endif
            @if ($channels)
                <div class="tbl">
                    @foreach ($channels as $c)<div><span class="k">{{ $c['k'] }}</span><span>{{ $c['v'] }}</span></div>@endforeach
                </div>
            @endif
        </div>
        <div class="card-ink" data-contact='@json($topics)'>
            <div class="bar-ink"><span>REQUEST FORM · RF-01</span><span data-sla>{{ $topics[$current][1] }}</span></div>
            @if (session('form_sent') === 'contact')
                <div class="sent" role="status">
                    <span class="tag-o">RECEIVED</span>
                    <div class="serif h-4">{{ $data['success'] ?? 'Thanks — it’s on the board.' }}</div>
                    <p class="lead" style="font-size: 16px">We’ll reply by email. {{ ucfirst(strtolower($topics[$current][1])) }}.</p>
                    <a class="btn btn--line" href="{{ url()->current() }}">Send another</a>
                </div>
            @else
                <form method="post" action="{{ route('site.forms.contact') }}">
                    @csrf
                    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="topics" role="radiogroup" aria-label="Topic">
                        @foreach ($topics as $key => $t)
                            <label><input type="radio" name="topic" value="{{ $key }}" @checked($current === $key)><span>{{ $t[0] }}</span></label>
                        @endforeach
                    </div>
                    <div class="frm" style="border: 0">
                        <div class="fr"><label for="c-name">Name</label><input id="c-name" name="name" value="{{ old('name') }}" placeholder="Your name" required autocomplete="name"></div>
                        @error('name')<div class="err">{{ $message }}</div>@enderror
                        <div class="fr"><label for="c-email">Email</label><input id="c-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@yourstore.com" required autocomplete="email"></div>
                        @error('email')<div class="err">{{ $message }}</div>@enderror
                        <div class="fr"><label for="c-store">Store URL</label><input id="c-store" name="store" value="{{ old('store') }}" placeholder="yourstore.myshopify.com"></div>
                        <div class="fr"><label for="c-extra" data-extra-label>{{ $topics[$current][2] }}</label><input id="c-extra" name="extra" value="{{ old('extra') }}" placeholder="{{ $topics[$current][3] }}" data-extra></div>
                        <div class="fr" style="border-bottom: 0"><label for="c-msg">Message</label><textarea id="c-msg" name="message" rows="5" placeholder="{{ $topics[$current][4] }}" required data-msg>{{ old('message') }}</textarea></div>
                        @error('message')<div class="err">{{ $message }}</div>@enderror
                        @error('form')<div class="err">{{ $message }}</div>@enderror
                        <button type="submit" class="submit" style="border-top: 1px solid #121212">Send request <span class="mono">→</span></button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</section>
@push('scripts')
<script>
(function () {
  var box = document.querySelector('[data-contact]'); if (!box) return;
  var T = JSON.parse(box.getAttribute('data-contact'));
  box.querySelectorAll('input[name=topic]').forEach(function (r) {
    r.addEventListener('change', function () {
      var t = T[r.value]; if (!t) return;
      box.querySelector('[data-sla]').textContent = t[1];
      var l = box.querySelector('[data-extra-label]'); if (l) l.textContent = t[2];
      var e = box.querySelector('[data-extra]'); if (e) e.placeholder = t[3];
      var m = box.querySelector('[data-msg]'); if (m) m.placeholder = t[4];
    });
  });
})();
</script>
@endpush
