@php $options = $data['options'] ?? [['name' => 'Metal', 'values' => '14k Yellow, 18k White, Platinum'], ['name' => 'Size', 'values' => '5, 6, 7, 8, 9'], ['name' => 'Carat', 'values' => '1.0, 1.5, 2.0']]; @endphp
<div class="variant-demo">
    <div class="vd-col">
        <header class="label">Your Shopify product · {{ collect($options)->reduce(fn ($c, $o) => $c * max(1, count(array_filter(array_map('trim', explode(',', $o['values']))))), 1) }} variants</header>
        @foreach ($options as $o)
            <div class="vd-row"><span>{{ $o['name'] }}</span><span class="vd-chips">@foreach (array_filter(array_map('trim', explode(',', $o['values']))) as $v)<span>{{ $v }}</span>@endforeach</span></div>
        @endforeach
    </div>
    <div class="vd-arrow" aria-hidden="true">→</div>
    <div class="vd-col">
        <header class="label">Composa configurator · one step per option</header>
        @foreach ($options as $i => $o)
            <div class="vd-row"><span>Step {{ $i + 1 }}</span><span>{{ $o['name'] }} — swatches, buttons or dropdown; sold-out combinations disabled</span></div>
        @endforeach
        <div class="vd-row"><span>Then</span><span>Your own steps: engraving, uploads, add-ons, price rules</span></div>
    </div>
</div>
