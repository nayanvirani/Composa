@php use App\Support\SiteText; @endphp
@extends('site.layout', ['minimal' => true, 'sheetNo' => '00', 'title' => 'Coming soon', 'seo' => ['robots' => 'noindex']])

@section('content')
    @if (! empty($canPreview))
        <div class="notice" style="background: #121212; color: #FFFFFF"><div class="wrap"><span class="mono" style="font-size: 12px">You’re signed in as staff. This is what visitors see.</span><a href="?preview=full" style="color: #FF6A2A; font-weight: 600">Preview the full site →</a></div></div>
    @endif
    <section class="sec paper" style="padding: 72px 0 96px">
        <div class="wrap nf" style="align-items: start">
            <div class="stack" style="gap: 24px">
                <span class="tag-o" style="font-size: 12px">{{ $launch['badge'] ?? 'LAUNCHING ON THE SHOPIFY APP STORE' }}</span>
                <h1 class="serif h-xl">{{ SiteText::inline($launch['title'] ?? 'Something made-to-order is *on the drawing board.*') }}</h1>
                @if (! empty($launch['intro']))<p class="lead" style="line-height: 1.6; max-width: 34em">{{ $launch['intro'] }}</p>@endif
                <div class="tbl">
                    <div><span class="k">Status</span><span>In final review</span></div>
                    <div><span class="k">Launch</span><span>{{ $launch['date_label'] ?? 'Soon' }}</span></div>
                    @if (! empty($site['contact']['sales_email']))<div><span class="k">Contact</span><span><a href="mailto:{{ $site['contact']['sales_email'] }}">{{ $site['contact']['sales_email'] }}</a></span></div>@endif
                </div>
            </div>
            <div class="card-ink">
                <div class="bar-ink"><span>NOTIFY ME · RF-00</span><span>ONE EMAIL AT LAUNCH</span></div>
                @if (in_array(session('form_sent'), ['newsletter', 'beta'], true))
                    <div class="sent" role="status">
                        <span class="tag-o">ON THE LIST</span>
                        <div class="serif h-4">Thanks — we’ll write when it’s live.</div>
                        <p class="lead" style="font-size: 16px">You’ll get one email the day Composa is listed on the Shopify App Store.</p>
                    </div>
                @else
                    <form method="post" action="{{ route('site.forms.beta') }}" class="frm" style="border: 0">
                        @csrf
                        <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="fr"><label for="cs-email">Email</label><input id="cs-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@yourstore.com" required autocomplete="email"></div>
                        @error('email')<div class="err">{{ $message }}</div>@enderror
                        <div class="fr"><label for="cs-store">Store</label><input id="cs-store" name="store" value="{{ old('store') }}" placeholder="yourstore.myshopify.com" required></div>
                        @error('store')<div class="err">{{ $message }}</div>@enderror
                        <div class="fr" style="border-bottom: 0"><label for="cs-make">You make</label><input id="cs-make" name="current_process" value="{{ old('current_process') }}" placeholder="e.g. custom furniture"></div>
                        @error('form')<div class="err">{{ $message }}</div>@enderror
                        <button type="submit" class="submit" style="border-top: 1px solid #121212">Notify me &amp; request early access <span class="mono">→</span></button>
                    </form>
                @endif
            </div>
        </div>
    </section>
    <section class="sec">
        <div class="wrap ledger">
            <div class="l">
                <div class="k" style="margin-bottom: 16px">§1 Without Composa</div>
                <div class="row"><span class="num">01</span><s>Hitting Shopify’s variant limits on every custom product</s></div>
                <div class="row"><span class="num">02</span><s>Fee products cluttering the cart, catalog and reports</s></div>
                <div class="row"><span class="num">03</span><s>Workshops decoding cart notes to find out what to make</s></div>
            </div>
            <div class="r">
                <div class="k" style="margin-bottom: 16px; color: #C23A00">§2 With Composa</div>
                <div class="row"><span class="num" style="color: #C23A00">01</span><span><strong style="font-weight: 600">Deep step flows.</strong> <span style="color: #5C5C57">Steps, rules and any input type, on your existing variants.</span></span></div>
                <div class="row"><span class="num" style="color: #C23A00">02</span><span><strong style="font-weight: 600">One clean cart line.</strong> <span style="color: #5C5C57">A signed price, verified at checkout.</span></span></div>
                <div class="row"><span class="num" style="color: #C23A00">03</span><span><strong style="font-weight: 600">A spec on every order.</strong> <span style="color: #5C5C57">Summary, configured image and a production PDF.</span></span></div>
            </div>
        </div>
    </section>
@endsection
