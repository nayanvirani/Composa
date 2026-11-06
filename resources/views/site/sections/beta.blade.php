@php
    use App\Support\SiteText;
    $cats = \App\Filament\Resources\TemplateResource::CATEGORIES;
    $steps = ['Store', 'Products', 'Needs'];
@endphp
<section class="sec paper" style="padding: 56px 0 72px">
    <div class="wrap bt">
        <div class="stack" style="gap: 24px">
            @if (! empty($data['sheet']))<div class="k">{{ $data['sheet'] }}</div>@endif
            <h1 class="serif h-l">{{ SiteText::inline($data['title'] ?? 'Help us draw *version one.*') }}</h1>
            @if (! empty($data['intro']))<p class="lead" style="line-height: 1.6">{{ $data['intro'] }}</p>@endif
            @if (! empty($data['timeline']))
                <div class="tbl">
                    @foreach ($data['timeline'] as $i => $t)
                        <div style="grid-template-columns: 60px minmax(0, 1fr)"><span class="mono" style="color: #C23A00; font-size: 12px">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><span><strong style="font-weight: 600">{{ $t['title'] }}</strong> <span style="color: #5C5C57">{{ $t['desc'] ?? '' }}</span></span></div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="card-ink">
            @if (session('form_sent') === 'beta')
                <div class="sent" role="status">
                    <span class="tag-o">APPLICATION RECEIVED</span>
                    <div class="serif h-4">{{ $data['success'] ?? 'You’re on the drawing board.' }}</div>
                    <p class="lead" style="font-size: 16px">We review every application by hand and reply within a week. If it’s a fit, we’ll book a setup call.</p>
                    <a class="btn btn--line" href="/docs">Read the docs meanwhile</a>
                </div>
            @else
                <form method="post" action="{{ route('site.forms.beta') }}" data-wizard>
                    @csrf
                    <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="wiz-tabs" aria-hidden="true">
                        @foreach ($steps as $i => $l)<div class="{{ $i === 0 ? 'on' : '' }}" data-tab="{{ $i }}">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }} · {{ strtoupper($l) }}</div>@endforeach
                    </div>
                    <div class="wiz-body">
                        @if ($errors->any())<div class="err" style="padding: 0">{{ $errors->first() }}</div>@endif
                        <fieldset class="on" data-step="0">
                            <legend class="sr">Your store</legend>
                            <div class="fld"><label for="b-store" class="k">Store URL</label><input id="b-store" type="text" name="store" value="{{ old('store') }}" placeholder="yourstore.myshopify.com" required></div>
                            <div class="fld"><label for="b-email" class="k">Email</label><input id="b-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@yourstore.com" required></div>
                            <div class="fld"><span class="k">Shopify plan</span><div class="pills">@foreach (['basic' => 'Basic', 'shopify' => 'Shopify', 'advanced' => 'Advanced', 'plus' => 'Plus'] as $v => $l)<label><input type="radio" name="shopify_plan" value="{{ $v }}" @checked(old('shopify_plan', 'shopify') === $v)>{{ $l }}</label>@endforeach</div></div>
                        </fieldset>
                        <fieldset data-step="1">
                            <legend class="sr">Your products</legend>
                            <div class="fld"><span class="k">What do you make? (pick any)</span><div class="pills">@foreach ($cats as $v => $l)<label><input type="checkbox" name="categories[]" value="{{ $v }}" @checked(in_array($v, old('categories', []), true))>{{ $l }}</label>@endforeach</div></div>
                            <div class="fld"><span class="k">Do your products already have Shopify variants?</span><div class="pills">@foreach (['yes' => 'Yes, many', 'some' => 'A few', 'no' => 'No'] as $v => $l)<label><input type="radio" name="has_variants" value="{{ $v }}" @checked(old('has_variants', 'yes') === $v)>{{ $l }}</label>@endforeach</div></div>
                        </fieldset>
                        <fieldset data-step="2">
                            <legend class="sr">Your needs</legend>
                            <div class="fld"><label for="b-today" class="k">How do you take custom orders today?</label><textarea id="b-today" name="current_process" rows="4" placeholder="e.g. email back and forth, a form, notes at checkout">{{ old('current_process') }}</textarea></div>
                            <div class="fld"><label for="b-want" class="k">What would make this a must-have?</label><textarea id="b-want" name="must_have" rows="3">{{ old('must_have') }}</textarea></div>
                        </fieldset>
                    </div>
                    <div class="wiz-nav">
                        <button type="button" class="back" data-back disabled>← Back</button>
                        <button type="submit" class="next" data-next>Next: Products <span class="mono">→</span></button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</section>
@push('scripts')
<script>
(function () {
  var f = document.querySelector('[data-wizard]'); if (!f) return;
  var L = ['Store', 'Products', 'Needs'], step = 0, back = f.querySelector('[data-back]'), next = f.querySelector('[data-next]');
  function go(n) {
    step = n;
    f.querySelectorAll('[data-step]').forEach(function (s) { s.classList.toggle('on', Number(s.getAttribute('data-step')) === n); });
    f.querySelectorAll('[data-tab]').forEach(function (t) { var i = Number(t.getAttribute('data-tab')); t.className = i === n ? 'on' : i < n ? 'done' : ''; });
    back.disabled = n === 0;
    next.firstChild.textContent = n === 2 ? 'Submit application ' : 'Next: ' + L[n + 1] + ' ';
  }
  next.addEventListener('click', function (e) {
    if (step === 2) return;
    e.preventDefault();
    var bad = Array.prototype.find.call(f.querySelectorAll('[data-step="' + step + '"] [required]'), function (i) { return !i.checkValidity(); });
    if (bad) { bad.reportValidity(); return; }
    go(step + 1);
  });
  back.addEventListener('click', function () { go(Math.max(0, step - 1)); });
})();
</script>
@endpush
