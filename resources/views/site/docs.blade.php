@extends('site.layout', ['title' => 'Docs', 'description' => 'Guides for building, pricing and launching product configurators with Composa.'])

@section('content')
    <section class="sec paper" style="padding: 56px 0">
        <div class="wrap stack" style="gap: 24px">
            <div class="k">Sheet 04 — Manual</div>
            <h1 class="serif h-l">How to compose <em>anything.</em></h1>
            <form class="search" action="{{ route('site.docs') }}" role="search">
                <label for="dq">SEARCH</label>
                <input id="dq" type="search" name="q" value="{{ $q }}" placeholder="e.g. variants, formula, theme, refund" data-docq autocomplete="off">
                <span class="cnt" data-doccount>{{ $results !== null ? $results->count() : $categories->sum(fn ($c) => $c->articles->count()) }} RESULTS</span>
                <button type="submit">Go</button>
            </form>
        </div>
    </section>

    @if ($results !== null)
        <section class="sec" style="padding: 40px 0">
            <div class="wrap stack" style="gap: 12px">
                <div class="k">{{ $results->count() }} result{{ $results->count() === 1 ? '' : 's' }} for “{{ $q }}”</div>
                <div class="list-links">
                    @forelse ($results as $a)
                        <a href="{{ route('site.doc', $a->slug) }}"><span><span class="mono">{{ $a->category?->title }}</span>{{ $a->title }}</span><span class="mono">→</span></a>
                    @empty
                        <a href="/contact"><span>Nothing found. Ask a human instead.</span><span class="mono">→</span></a>
                    @endforelse
                </div>
            </div>
        </section>
    @endif

    <section class="sec" style="padding: 56px 0">
        <div class="wrap">
            <div class="cats">
                @foreach ($categories as $ci => $cat)
                    <div data-doccat>
                        <div style="display: flex; justify-content: space-between; align-items: baseline"><span class="serif" style="font-size: 48px; line-height: 1; color: #FF4F00">{{ str_pad($ci + 1, 2, '0', STR_PAD_LEFT) }}</span><span class="k">{{ $cat->articles->count() }} articles</span></div>
                        <div style="font-size: 20px; font-weight: 600">{{ $cat->title }}</div>
                        @foreach ($cat->articles as $ai => $a)
                            <a class="it" href="{{ route('site.doc', $a->slug) }}" data-docitem="{{ strtolower($a->title.' '.$cat->title) }}"><span class="mono">{{ $ci + 1 }}.{{ $ai + 1 }}</span><span>{{ $a->title }}</span></a>
                        @endforeach
                        <span style="font-size: 13px; color: #5C5C57" hidden data-docnone>No matches here</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="sec" style="padding: 56px 0; background: #F5F5F2">
        <div class="wrap" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px">
            @if ($startHere->isNotEmpty())
                <div class="stack start" style="gap: 12px">
                    <h2 class="serif" style="font-size: 40px; line-height: 1">Start here</h2>
                    @foreach ($startHere as $i => $a)
                        <a href="{{ route('site.doc', $a->slug) }}"><span class="n">{{ $i + 1 }}</span><span style="flex: 1; font-weight: 500">{{ $a->title }}</span><span class="mono" style="font-size: 12px; color: #5C5C57">{{ $a->read_minutes }} MIN</span></a>
                    @endforeach
                </div>
            @endif
            <div class="stack" style="border: 1px solid #121212; background: #121212; color: #EDEDE8; padding: 24px; gap: 12px">
                <span class="k" style="color: #A3A39C">Still stuck?</span>
                <div class="serif" style="font-size: 36px; line-height: 1">Ask a human.</div>
                <p style="margin: 0; font-size: 15px; line-height: 1.55; color: #BDBDB6">Email support on Starter, priority on Growth and Pro, and a setup call on Pro.</p>
                <a href="/contact" class="btn btn--orange" style="align-self: flex-start; min-height: 44px">Contact support <span class="arr">→</span></a>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
(function () {
  var q = document.querySelector('[data-docq]'); if (!q) return;
  q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase(), n = 0;
    document.querySelectorAll('[data-doccat]').forEach(function (c) {
      var hits = 0;
      c.querySelectorAll('[data-docitem]').forEach(function (a) { var on = !v || a.getAttribute('data-docitem').indexOf(v) >= 0; a.hidden = !on; if (on) hits++; });
      c.querySelector('[data-docnone]').hidden = hits > 0; c.style.opacity = v && !hits ? .45 : 1; n += hits;
    });
    document.querySelector('[data-doccount]').textContent = n + ' RESULTS';
  });
})();
</script>
@endpush
