{{-- Numbered section frame: "§02  HOW IT WORKS ……… SHEET 02 / 07" --}}
<section class="sheet" @isset($id) id="{{ $id }}" @endisset>
    <div class="wrap">
        <div class="sheet-head">
            <span class="num">§{{ str_pad($n, 2, '0', STR_PAD_LEFT) }}</span>
            <span class="label">{{ $eyebrow ?: $kind }}</span>
            <span class="label">Sheet {{ str_pad($n, 2, '0', STR_PAD_LEFT) }} / {{ str_pad($total, 2, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div class="sheet-body">
            @if (! empty($title))
                <div class="sheet-title">
                    <span></span>
                    <div>
                        <h2>{{ \App\Support\SiteText::inline($title) }}</h2>
                        @if (! empty($intro))<p>{{ $intro }}</p>@endif
                    </div>
                </div>
            @endif
            {{ $slot }}
        </div>
    </div>
</section>
