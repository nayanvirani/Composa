@php
    use App\Support\TemplateMeta;
    $cats = \App\Filament\Resources\TemplateResource::CATEGORIES;
    $plans = \App\Models\Plan::query()->pluck('name', 'handle');
    $templates = \App\Models\Template::query()->where('status', 'published')->where('key', '!=', 'blank')->with('currentVersion')->orderByDesc('featured')->orderBy('sort')->get()->values();
    $rots = ['-1.2deg', '0.8deg', '-0.4deg', '1.2deg', '-0.8deg', '0.5deg'];
    $items = $templates->map(function ($t, $i) use ($cats, $plans) {
        $def = $t->currentVersion?->definition ?? [];
        return ['key' => $t->key, 'no' => 'P-'.str_pad($i + 1, 2, '0', STR_PAD_LEFT), 'n' => $t->name, 'cat' => $cats[$t->category] ?? ucfirst($t->category), 'catKey' => $t->category,
            'body' => $t->description, 'pricing' => TemplateMeta::pricing($def), 'plan' => $t->min_plan ? ($plans[$t->min_plan] ?? $t->min_plan) : 'Any plan', 'steps' => TemplateMeta::steps($def)];
    });
    $first = $items->first();
@endphp
<section class="sec paper" style="padding: 32px 0 80px">
    <div class="wrap" style="margin-bottom: 32px">
        <div class="seg" role="tablist" aria-label="Category" data-libtabs>
            <button type="button" role="tab" aria-selected="true" data-cat="all">All <span class="c">{{ $items->count() }}</span></button>
            @foreach ($cats as $key => $label)
                @php $c = $items->where('catKey', $key)->count(); @endphp
                @if ($c)<button type="button" role="tab" aria-selected="false" data-cat="{{ $key }}">{{ $label }} <span class="c">{{ $c }}</span></button>@endif
            @endforeach
        </div>
    </div>
    <div class="wrap tg2" data-lib='@json($items)'>
        <div class="lib">
            @foreach ($items as $i => $t)
                <button type="button" id="{{ $t['key'] }}" class="pin" data-i="{{ $i }}" data-cat="{{ $t['catKey'] }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}" style="transform: rotate({{ $rots[$i % count($rots)] }})">
                    <span class="no">{{ $t['no'] }} · {{ $t['cat'] }}</span>
                    <span class="nm" style="font-size: 32px">{{ $t['n'] }}</span>
                    <span class="ds">{{ \Illuminate\Support\Str::limit($t['body'], 80) }}</span>
                    <span class="mt">{{ count($t['steps']) }} STEPS · {{ strtoupper($t['pricing']) }}</span>
                </button>
            @endforeach
        </div>
        @if ($first)
            <aside class="card-ink" aria-live="polite">
                <div class="bar-ink"><span>SPEC — <span data-f="no">{{ $first['no'] }}</span></span><span data-f="cat">{{ $first['cat'] }}</span></div>
                <div class="stack" style="padding: 20px 18px; gap: 14px">
                    <div class="serif h-4" data-f="n">{{ $first['n'] }}</div>
                    <p class="lead" style="font-size: 15px" data-f="body">{{ $first['body'] }}</p>
                    <div data-f="steps">
                        @foreach ($first['steps'] as $j => [$st, $kind])
                            <div class="step-row"><span class="mono">{{ str_pad($j + 1, 2, '0', STR_PAD_LEFT) }}</span><span style="font-weight: 500">{{ $st }}</span><span class="mono" style="font-size: 11px; color: #5C5C57">{{ $kind }}</span></div>
                        @endforeach
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid #121212">
                        <div style="padding: 10px 12px; border-right: 1px solid #121212"><div class="mono" style="font-size: 10px; color: #5C5C57">PRICING</div><div style="font-weight: 500" data-f="pricing">{{ $first['pricing'] }}</div></div>
                        <div style="padding: 10px 12px"><div class="mono" style="font-size: 10px; color: #5C5C57">FROM PLAN</div><div style="font-weight: 500" data-f="plan">{{ $first['plan'] }}</div></div>
                    </div>
                    <a class="btn" style="justify-content: center" href="{{ $site['links']['install'] }}">Install pattern <span class="arr">→</span></a>
                </div>
            </aside>
        @endif
    </div>
</section>
@push('scripts')
<script>
(function () {
  var lib = document.querySelector('[data-lib]'); if (!lib) return;
  var T = JSON.parse(lib.getAttribute('data-lib'));
  var pad = function (n) { return String(n).padStart(2, '0'); };
  function show(i) {
    var t = T[i];
    lib.querySelectorAll('.pin').forEach(function (p) { p.setAttribute('aria-pressed', Number(p.getAttribute('data-i')) === i); });
    ['no', 'cat', 'n', 'body', 'pricing', 'plan'].forEach(function (k) { var e = lib.querySelector('[data-f=' + k + ']'); if (e) e.textContent = t[k] || ''; });
    var st = lib.querySelector('[data-f=steps]'); st.innerHTML = '';
    t.steps.forEach(function (s, j) {
      var row = document.createElement('div'); row.className = 'step-row';
      [[pad(j + 1), 'mono'], [s[0], ''], [s[1], 'mono']].forEach(function (c, k) { var sp = document.createElement('span'); sp.textContent = c[0]; if (c[1]) sp.className = c[1]; if (k === 1) sp.style.fontWeight = 500; if (k === 2) { sp.style.fontSize = '11px'; sp.style.color = '#5C5C57'; } row.appendChild(sp); });
      st.appendChild(row);
    });
  }
  lib.querySelectorAll('.pin').forEach(function (p) { p.addEventListener('click', function () { show(Number(p.getAttribute('data-i'))); }); });
  document.querySelectorAll('[data-libtabs] [data-cat]').forEach(function (b) {
    b.addEventListener('click', function () {
      var c = b.getAttribute('data-cat'), first = -1;
      document.querySelectorAll('[data-libtabs] [data-cat]').forEach(function (x) { x.setAttribute('aria-selected', x === b); });
      lib.querySelectorAll('.pin').forEach(function (p) { var on = c === 'all' || p.getAttribute('data-cat') === c; p.hidden = !on; if (on && first < 0) first = Number(p.getAttribute('data-i')); });
      if (first >= 0) show(first);
    });
  });
  var hash = location.hash.slice(1), idx = T.findIndex(function (t) { return t.key === hash; });
  if (idx >= 0) show(idx);
})();
</script>
@endpush
