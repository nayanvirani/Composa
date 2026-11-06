@php
    use App\Support\SiteText;
    // Fig. 1 — a working mini-configurator. Products and prices are illustrative and live in the page data.
    $demo = [
        ['n' => 'Ring', 'code' => 'RING-SOL-04', 'base' => 950, 'title' => 'Solitaire Ring — Custom', 'tones' => ['#E3C16F', '#D9A08B', '#C9CED4'], 'toneFrom' => 1, 'dots' => [[204, 70], [266, 200], [150, 92], [180, 270]], 'pick' => [1, 2, 1, 1], 'parts' => [
            ['Shape', [['Round', 0], ['Oval', 40], ['Princess', 60]]], ['Metal', [['14K Yellow', 0], ['18K Rose', 320], ['Platinum', 610]]],
            ['Stone', [['0.5 ct', 0], ['1.0 ct', 780], ['1.5 ct', 1620]]], ['Engraving', [['None', 0], ['“Forever”', 10.5], ['“A + J 2026”', 15]]]]],
        ['n' => 'Sofa', 'code' => 'SOFA-MOD-02', 'base' => 1490, 'title' => 'Modular Sofa — Custom', 'tones' => ['#E7DFD2', '#B9C6B0', '#2C3240'], 'toneFrom' => 0, 'dots' => [[180, 112], [110, 182], [54, 248], [180, 288]], 'pick' => [1, 0, 1, 1], 'parts' => [
            ['Fabric', [['Bouclé oat', 0], ['Linen sage', 120], ['Velvet ink', 260]]], ['Modules', [['2-seat', 0], ['3-seat', 540], ['Corner', 980]]],
            ['Legs', [['Black steel', 0], ['Walnut', 90], ['Brass', 150]]], ['Width', [['180 cm', 0], ['220 cm', 160], ['260 cm', 320]]]]],
        ['n' => 'Hoodie', 'code' => 'HOOD-PRM-60', 'base' => 49, 'title' => 'Premium Hoodie — Custom', 'tones' => ['#2A2A2A', '#B9BCC0', '#22324F'], 'toneFrom' => 0, 'dots' => [[110, 150], [304, 120], [180, 238], [180, 106]], 'pick' => [2, 2, 1, 1], 'parts' => [
            ['Color', [['Black', 0], ['Heather', 0], ['Navy', 0]]], ['Size', [['S', 0], ['M', 0], ['XXL', 4]]],
            ['Material', [['Fleece', 0], ['Organic', 10], ['Recycled', 6]]], ['Embroidery', [['None', 0], ['“NORTHFIELD”', 8], ['Logo upload', 12]]]]],
        ['n' => 'Poster', 'code' => 'PRNT-FRM-11', 'base' => 39, 'title' => 'Framed Print — Custom', 'tones' => ['#C8A36E', '#1C1C1C', '#FFFFFF'], 'toneFrom' => 2, 'dots' => [[64, 148], [180, 238], [92, 34], [180, 120]], 'pick' => [1, 0, 1, 0], 'parts' => [
            ['Size', [['30×40', 0], ['50×70', 40], ['70×100', 90]]], ['Paper', [['Matte 200g', 0], ['Fine art 310g', 25], ['Canvas', 45]]],
            ['Frame', [['Oak', 50], ['Black', 40], ['None', 0]]], ['Artwork', [['upload.jpg', 0], ['Pet portrait', 30], ['Map of city', 20]]]]],
    ];
@endphp
<section class="sec paper" style="padding: 56px 0 72px">
    <div class="wrap stack" style="gap: 36px">
        <div class="sec-head">
            <h1 class="serif h-xl" style="max-width: 9.5em">{{ SiteText::inline($data['title'] ?? '') }}</h1>
            <div class="stack" style="max-width: 360px; gap: 16px; padding-bottom: 8px">
                @if (! empty($data['intro']))<p class="lead">{{ $data['intro'] }}</p>@endif
                <div style="display: flex; gap: 10px; flex-wrap: wrap">
                    <a class="btn" href="{{ SiteText::url($data['primary_url'] ?? $site['links']['install']) }}">{{ $data['primary_label'] ?? 'Install on Shopify' }} <span class="arr">→</span></a>
                    @if (! empty($data['secondary_label']))<a class="btn btn--line" href="{{ SiteText::url($data['secondary_url'] ?? '/templates') }}">{{ $data['secondary_label'] }}</a>@endif
                </div>
            </div>
        </div>

        <div class="hero" data-composer='@json($demo)'>
            <div class="stack" style="border-right: 1px solid #121212">
                <div class="bar-ink" style="padding: 10px 14px; text-transform: uppercase"><span>Fig. 1 — Compose one</span><span style="color: #5C5C57">Click a part to change it</span></div>
                <div class="tabs" role="tablist" aria-label="Product to compose">
                    @foreach ($demo as $i => $c)
                        <button type="button" role="tab" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" data-cat="{{ $i }}">{{ $c['n'] }}</button>
                    @endforeach
                </div>
                <div class="stack" style="flex: 1" data-parts>
                    @foreach ($demo[0]['parts'] as $i => [$k, $vals])
                        @php $v = $vals[$demo[0]['pick'][$i]]; @endphp
                        <button type="button" class="part" data-part="{{ $i }}">
                            <span class="dot">{{ $i + 1 }}</span>
                            <span><span class="pk">{{ $k }}</span><span class="pv">{{ $v[0] }}</span></span>
                            <span class="pd">{{ $v[1] ? '+$'.number_format($v[1], 2) : 'incl.' }} ↻</span>
                        </button>
                    @endforeach
                    <div class="hero-total"><span class="k">Total · signed</span><span class="serif" data-total></span></div>
                </div>
            </div>
            <div style="display: grid; grid-template-rows: minmax(0, 1fr) auto; min-height: 520px">
                <div class="paper drawing">
                    <svg width="420" height="340" viewBox="0 0 360 300" role="img" aria-label="Technical drawing of the configured product with numbered parts" style="height: auto">
                        <g fill="none" stroke="#121212" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round">
                            <g data-shape="0"><ellipse cx="180" cy="185" rx="82" ry="86" stroke-width="10" data-tone></ellipse><ellipse cx="180" cy="185" rx="82" ry="86"></ellipse><ellipse cx="180" cy="185" rx="70" ry="74"></ellipse><path d="M156 100 L180 64 L204 100 L180 120 Z" fill="#FFFFFF"></path><path d="M156 100 L204 100 M180 64 L172 100 L180 120 L188 100 Z"></path><path d="M120 252 Q180 280 240 252" stroke-dasharray="4 4"></path></g>
                            <g data-shape="1" style="display: none"><rect x="50" y="90" width="260" height="70" rx="10" data-tone-fill></rect><rect x="34" y="120" width="40" height="100" rx="8" fill="#FFFFFF"></rect><rect x="286" y="120" width="40" height="100" rx="8" fill="#FFFFFF"></rect><rect x="74" y="160" width="212" height="44" rx="6" fill="#FFFFFF"></rect><path d="M144 160 L144 204 M216 160 L216 204"></path><path d="M60 220 L54 248 M300 220 L306 248"></path><path d="M34 272 L326 272 M34 266 L34 278 M326 266 L326 278"></path></g>
                            <g data-shape="2" style="display: none"><path d="M130 40 Q180 4 230 40 L294 76 L332 190 L296 204 L276 142 L276 280 L84 280 L84 142 L64 204 L28 190 L66 76 Z" data-tone-fill></path><path d="M140 46 Q180 92 220 46"></path><path d="M120 214 L240 214 L232 262 L128 262 Z" stroke-dasharray="4 4"></path><path d="M150 122 L210 122" stroke="#FF4F00" stroke-width="2.4"></path></g>
                            <g data-shape="3" style="display: none"><rect x="90" y="30" width="180" height="236" data-tone-fill></rect><rect x="106" y="46" width="148" height="204" fill="#FFFFFF"></rect><rect x="122" y="62" width="116" height="150" fill="#F2F2EE"></rect><path d="M122 180 L160 140 L190 168 L212 150 L238 176"></path><circle cx="210" cy="96" r="12"></circle><path d="M64 30 L64 266 M58 30 L70 30 M58 266 L70 266"></path></g>
                        </g>
                        <g data-dots></g>
                    </svg>
                    <div class="mono" style="position: absolute; left: 14px; bottom: 12px; font-size: 11px; color: #5C5C57">SCALE NTS · <span data-code></span></div>
                </div>
                <div class="cartline">
                    <div class="ln">
                        <div style="color: #121212; font-weight: 500">CART LINE 1 OF 1 — <span data-title></span></div>
                        <div data-props></div>
                        <div style="color: #5C5C57">_config_id cfg_<span data-cfg></span> · _sig ✓ verified · no fee products</div>
                    </div>
                    <div class="qty"><span class="mono" style="font-size: 11px; color: #5C5C57">QTY 1</span><span style="font-size: 20px; font-weight: 600" data-total></span></div>
                </div>
            </div>
        </div>
    </div>
</section>
@push('scripts')
<script>
(function () {
  var root = document.querySelector('[data-composer]'); if (!root) return;
  var C = JSON.parse(root.getAttribute('data-composer')), cat = 0, picks = C.map(function (c) { return c.pick.slice(); });
  var money = function (n) { return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var ns = 'http://www.w3.org/2000/svg';
  function render() {
    var c = C[cat], p = picks[cat];
    var total = c.base + c.parts.reduce(function (a, part, i) { return a + part[1][p[i]][1]; }, 0);
    root.querySelectorAll('[role=tab]').forEach(function (t, i) { t.setAttribute('aria-selected', i === cat); });
    root.querySelectorAll('[data-part]').forEach(function (b, i) {
      var v = c.parts[i][1][p[i]];
      b.querySelector('.pk').textContent = c.parts[i][0];
      b.querySelector('.pv').textContent = v[0];
      b.querySelector('.pd').textContent = (v[1] ? '+' + money(v[1]) : 'incl.') + ' ↻';
      b.setAttribute('aria-label', c.parts[i][0] + ': ' + v[0] + '. Click to change');
    });
    root.querySelectorAll('[data-shape]').forEach(function (g) { g.style.display = Number(g.getAttribute('data-shape')) === cat ? '' : 'none'; });
    var tone = c.tones[p[c.toneFrom]];
    root.querySelectorAll('[data-tone]').forEach(function (e) { e.setAttribute('stroke', tone); });
    root.querySelectorAll('[data-tone-fill]').forEach(function (e) { e.setAttribute('fill', tone); });
    var dots = root.querySelector('[data-dots]'); dots.innerHTML = '';
    c.dots.forEach(function (d, i) {
      var g = document.createElementNS(ns, 'g'), ci = document.createElementNS(ns, 'circle'), tx = document.createElementNS(ns, 'text');
      ci.setAttribute('cx', d[0]); ci.setAttribute('cy', d[1]); ci.setAttribute('r', 11); ci.setAttribute('fill', '#FF4F00');
      tx.setAttribute('x', d[0]); tx.setAttribute('y', d[1] + 4); tx.setAttribute('text-anchor', 'middle'); tx.setAttribute('font-family', 'Geist Mono, monospace'); tx.setAttribute('font-size', 11); tx.setAttribute('fill', '#121212'); tx.textContent = i + 1;
      g.appendChild(ci); g.appendChild(tx); dots.appendChild(g);
    });
    root.querySelectorAll('[data-total]').forEach(function (e) { e.textContent = money(total); });
    root.querySelector('[data-code]').textContent = c.code;
    root.querySelector('[data-title]').textContent = c.title.toUpperCase();
    root.querySelector('[data-props]').textContent = c.parts.map(function (part, i) { return part[0] + ': ' + part[1][p[i]][0]; }).join(' · ');
    root.querySelector('[data-cfg]').textContent = (8191 * (cat + 3) + p.reduce(function (a, x, i) { return a + x * (i + 7) * 131; }, 0)).toString(16);
  }
  root.querySelectorAll('[role=tab]').forEach(function (t) { t.addEventListener('click', function () { cat = Number(t.getAttribute('data-cat')); render(); }); });
  root.querySelectorAll('[data-part]').forEach(function (b) { b.addEventListener('click', function () { var i = Number(b.getAttribute('data-part')); picks[cat][i] = (picks[cat][i] + 1) % C[cat].parts[i][1].length; render(); }); });
  render();
})();
</script>
@endpush
