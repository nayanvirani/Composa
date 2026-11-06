@php
    use App\Support\SiteText;
    $plans = \App\Models\Plan::query()->where('is_public', true)->orderBy('sort')->get();
    $level = fn ($p) => collect($p->feature('pricing_types', []))->map(fn ($t) => ['fixed' => 0, 'percent' => 0, 'per_char' => 1, 'per_unit' => 2, 'tiered' => 2, 'table' => 2, 'formula' => 3][$t] ?? 0)->max() ?? 0;
    $num = fn ($v) => $v === null ? '∞' : number_format($v);
    $gb = fn ($mb) => $mb === null ? 'Unlimited' : ($mb ? ($mb >= 1024 ? round($mb / 1024).' GB' : $mb.' MB') : '—');
    $money = fn ($c) => '$'.rtrim(rtrim(number_format($c / 100, 2), '0'), '.');
    $ptypes = ['Fixed & %', 'Per character', 'Tiers & tables', 'Formula'];
    $planData = $plans->map(fn ($p) => ['lim' => [$p->limit('configurators') ?? PHP_INT_MAX, $p->limit('products') ?? PHP_INT_MAX, $p->limit('steps') ?? PHP_INT_MAX, $level($p)]])->values();
    $typeList = function ($p) {
        $t = $p->feature('pricing_types', []);
        $names = ['fixed' => 'Fixed', 'percent' => '%', 'per_char' => 'per char', 'per_unit' => 'per unit', 'tiered' => 'tiered', 'table' => 'table', 'formula' => 'formula'];

        return implode(', ', array_map(fn ($x) => $names[$x] ?? $x, $t));
    };
    $rows = [
        ['Published configurators', fn ($p) => $num($p->limit('configurators'))],
        ['Draft configurators', fn ($p) => $num($p->limit('drafts'))],
        ['Steps per configurator', fn ($p) => $num($p->limit('steps'))],
        ['Options per configurator', fn ($p) => $num($p->limit('options'))],
        ['Rules per configurator', fn ($p) => $num($p->limit('rules')).' '.($p->feature('rules_level') === 'advanced' ? 'nested' : 'basic')],
        ['Assigned products', fn ($p) => $num($p->limit('products'))],
        ['Shopify variant mapping', fn ($p) => '✓'],
        ['Nested & repeatable steps', fn ($p) => $p->feature('nested_steps') ? '✓' : '—'],
        ['Pricing types', $typeList],
        ['File uploads', fn ($p) => $p->feature('uploads') ? $gb($p->limit('storage_mb')) : '—'],
        ['Languages', fn ($p) => $p->limit('languages') === null ? 'All' : $p->limit('languages')],
        ['Production PDF', fn ($p) => $p->feature('pdf') ? ($p->feature('pdf_branding') ? '✓ branded' : '✓') : '—'],
        ['Analytics', fn ($p) => ['none' => '—', 'basic' => 'Basic', 'full' => 'Full funnel', 'export' => 'Full + export'][$p->feature('analytics', 'none')] ?? '—'],
        ['Hidden / fee products', fn ($p) => 'Never'],
    ];
@endphp
<section class="sec paper" style="padding: 56px 0 64px">
    <div class="wrap stack" style="gap: 28px">
        <div class="sec-head">
            <div class="stack" style="gap: 10px">
                @if (! empty($data['sheet']))<div class="k">{{ $data['sheet'] }}</div>@endif
                <h1 class="serif h-l">{{ SiteText::inline($data['title'] ?? 'Specify your *requirements.*') }}</h1>
            </div>
            @if (! empty($data['intro']))<p class="lead" style="max-width: 340px; font-size: 16px">{{ $data['intro'] }}</p>@endif
        </div>
        <div class="finder" data-finder='@json($planData)'>
            <div><label for="r-cfg" class="k">A · Configurators</label><output class="val" for="r-cfg" data-out="cfg">4</output><input id="r-cfg" type="range" min="1" max="60" value="4" data-in="cfg"></div>
            <div><label for="r-prod" class="k">B · Products to assign</label><output class="val" for="r-prod" data-out="prod">120</output><input id="r-prod" type="range" min="0" max="8" value="4" data-in="prod"></div>
            <div><label for="r-steps" class="k">C · Steps in largest</label><output class="val" for="r-steps" data-out="steps">8</output><input id="r-steps" type="range" min="2" max="50" value="8" data-in="steps"></div>
            <div><span class="k">D · Pricing you need</span><div style="display: flex; flex-wrap: wrap; gap: 6px" role="group" aria-label="Pricing you need">@foreach ($ptypes as $i => $t)<button type="button" class="chip" aria-pressed="{{ $i === 2 ? 'true' : 'false' }}" data-ptype="{{ $i }}">{{ $t }}</button>@endforeach</div></div>
        </div>
    </div>
</section>

<section class="sec" style="padding: 64px 0">
    <div class="wrap stack" style="gap: 20px">
        <div class="plans" style="--n: {{ max(1, $plans->count()) }}">
            @foreach ($plans as $i => $p)
                <div class="plan" data-plan="{{ $i }}">
                    <div class="top">
                        <div style="display: flex; justify-content: space-between; align-items: center"><span class="mono" style="font-size: 12px">PL-{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</span><span class="rec">MATCHES YOUR SPEC</span></div>
                        <div class="nm">{{ $p->name }}</div>
                        <div class="tg">{{ $p->tagline }}</div>
                        <div class="pr"><span class="serif">{{ $p->price_cents ? $money($p->price_cents) : '$0' }}</span><span class="mono" style="font-size: 12px; color: #5C5C57">/ MONTH</span></div>
                        <a class="cta" href="{{ $site['links']['install'] }}">{{ $p->price_cents ? ($p->trial_days ? 'Start trial' : 'Install') : 'Install' }} <span class="mono">→</span></a>
                    </div>
                    <div class="lr" data-row="0"><span>Configurators</span><span>{{ $num($p->limit('configurators')) }}</span></div>
                    <div class="lr" data-row="1"><span>Assigned products</span><span>{{ $num($p->limit('products')) }}</span></div>
                    <div class="lr" data-row="2"><span>Steps each</span><span>{{ $num($p->limit('steps')) }}</span></div>
                    <div class="lr" data-row="3"><span>Pricing</span><span>{{ $ptypes[$level($p)] }}{{ $level($p) ? ' + below' : '' }}</span></div>
                    <div class="lr"><span>Uploads</span><span>{{ $p->feature('uploads') ? $gb($p->limit('storage_mb')) : '—' }}</span></div>
                    <div class="lr"><span>Languages</span><span>{{ $p->limit('languages') === null ? 'All' : $p->limit('languages') }}</span></div>
                    <div class="lr"><span>Support</span><span>{{ $p->feature('support') }}</span></div>
                </div>
            @endforeach
        </div>
        <div class="mono" style="font-size: 12px; color: #5C5C57">{{ $data['footnote'] ?? 'Prices in USD · billed through Shopify · live configurators never stop at a limit' }} · need more than {{ $plans->last()?->name }}? <a href="/contact">Talk to us</a></div>
    </div>
</section>

<section class="sec" style="padding: 72px 0; background: #F5F5F2">
    <div class="wrap stack" style="gap: 24px">
        <h2 class="serif h-3">{{ $data['table_title'] ?? 'Full specification' }}</h2>
        <div class="scroll-x" style="background: #FFFFFF; border: 1px solid #121212">
            <table class="cmp">
                <thead><tr><th scope="col">Item</th>@foreach ($plans as $p)<th scope="col">{{ $p->name }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($rows as $n => [$label, $fn])
                        <tr><th scope="row"><span class="mono">{{ str_pad($n + 1, 2, '0', STR_PAD_LEFT) }}</span>{{ $label }}</th>@foreach ($plans as $p)<td>{{ $fn($p) }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@push('scripts')
<script>
(function () {
  var f = document.querySelector('[data-finder]'); if (!f) return;
  var P = JSON.parse(f.getAttribute('data-finder')), prodSteps = [5, 10, 25, 50, 120, 300, 500, 1500, 5000], ptype = 2;
  var get = function (k) { return Number(f.querySelector('[data-in=' + k + ']').value); };
  function run() {
    var s = { cfg: get('cfg'), prod: prodSteps[get('prod')], steps: get('steps'), ptype: ptype };
    f.querySelector('[data-out=cfg]').textContent = s.cfg;
    f.querySelector('[data-out=prod]').textContent = s.prod.toLocaleString('en-US');
    f.querySelector('[data-out=steps]').textContent = s.steps;
    var need = [s.cfg, s.prod, s.steps, s.ptype];
    var fits = function (p) { return need.every(function (v, i) { return v <= p.lim[i]; }); };
    var rec = P.findIndex(fits); if (rec < 0) rec = P.length - 1;
    document.querySelectorAll('[data-plan]').forEach(function (el, i) {
      el.classList.toggle('is-rec', i === rec);
      el.querySelectorAll('[data-row]').forEach(function (r) { var k = Number(r.getAttribute('data-row')); r.classList.toggle('over', need[k] > P[i].lim[k]); });
    });
  }
  f.querySelectorAll('input[type=range]').forEach(function (i) { i.addEventListener('input', run); });
  f.querySelectorAll('[data-ptype]').forEach(function (b) { b.addEventListener('click', function () { ptype = Number(b.getAttribute('data-ptype')); f.querySelectorAll('[data-ptype]').forEach(function (x) { x.setAttribute('aria-pressed', x === b); }); run(); }); });
  run();
})();
</script>
@endpush
