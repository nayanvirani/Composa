@php
    $plans = \App\Models\Plan::query()->where('is_public', true)->orderBy('sort')->get();
    $labels = \App\Support\PlanCatalog::LIMIT_LABELS;
    $fmt = fn ($v, $key) => $v === null ? 'Unlimited' : ($key === 'storage_mb' ? ($v >= 1024 ? round($v / 1024).' GB' : ($v ? $v.' MB' : '—')) : number_format($v));
@endphp
<div class="plans" style="--plans: {{ max(1, $plans->count()) }}">
    @foreach ($plans as $plan)
        <div class="plan {{ $plan->is_featured ? 'is-featured' : '' }}">
            <div>
                <div class="label">{{ $plan->is_featured ? 'Most chosen' : ($plan->dev_only ? 'Development stores' : 'Plan') }}</div>
                <h3 style="font-size:24px;margin-top:8px">{{ $plan->name }}</h3>
                @if ($plan->tagline)<p class="muted" style="margin-top:6px;font-size:14px">{{ $plan->tagline }}</p>@endif
            </div>
            <div class="price">{{ $plan->price_cents ? '$'.rtrim(rtrim(number_format($plan->price_cents / 100, 2), '0'), '.') : 'Free' }} @if ($plan->price_cents)<small>/ month</small>@endif</div>
            @if ($plan->trial_days)<div class="label">{{ $plan->trial_days }}-day free trial</div>@endif
            <ul>
                <li><span>Published configurators</span><strong>{{ $fmt($plan->limit('configurators'), 'configurators') }}</strong></li>
                <li><span>Options each</span><strong>{{ $fmt($plan->limit('options'), 'options') }}</strong></li>
                <li><span>Assigned products</span><strong>{{ $fmt($plan->limit('products'), 'products') }}</strong></li>
                <li><span>Support</span><strong>{{ $plan->feature('support') }}</strong></li>
            </ul>
            <a class="btn" href="{{ $site['links']['install'] }}">{{ $plan->price_cents ? 'Start free trial' : 'Install' }}</a>
        </div>
    @endforeach
</div>
@if ($data['show_limits'] ?? true)
    <div class="table-scroll">
        <table class="limits">
            <thead><tr><th scope="col">Limit</th>@foreach ($plans as $plan)<th scope="col">{{ $plan->name }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($labels as $key => $label)
                    <tr><th scope="row" style="font-weight:400">{{ $label }}</th>@foreach ($plans as $plan)<td>{{ $fmt($plan->limit($key), $key) }}</td>@endforeach</tr>
                @endforeach
                <tr><th scope="row" style="font-weight:400">Pricing types</th>@foreach ($plans as $plan)<td>{{ count($plan->feature('pricing_types', [])) }} of 7</td>@endforeach</tr>
                <tr><th scope="row" style="font-weight:400">Conditional rules</th>@foreach ($plans as $plan)<td>{{ ucfirst($plan->feature('rules_level', 'basic')) }}</td>@endforeach</tr>
                <tr><th scope="row" style="font-weight:400">File uploads</th>@foreach ($plans as $plan)<td>{{ $plan->feature('uploads') ? 'Yes' : '—' }}</td>@endforeach</tr>
                <tr><th scope="row" style="font-weight:400">Production PDF</th>@foreach ($plans as $plan)<td>{{ $plan->feature('pdf') ? ($plan->feature('pdf_branding') ? 'Own branding' : 'Yes') : '—' }}</td>@endforeach</tr>
                <tr><th scope="row" style="font-weight:400">Analytics</th>@foreach ($plans as $plan)<td>{{ ucfirst($plan->feature('analytics', 'none')) }}</td>@endforeach</tr>
            </tbody>
        </table>
    </div>
@endif
<p class="footnote">{{ $data['footnote'] ?? 'Billed through Shopify. Prices in USD.' }} You’re warned at 80% of a limit; at 100% new items of that kind pause until you upgrade.</p>
