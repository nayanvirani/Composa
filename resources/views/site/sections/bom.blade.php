<section class="sec sec-pad">
    <div class="wrap stack" style="gap: 32px">
        <div class="sec-head">
            <h2 class="serif h-2">{{ \App\Support\SiteText::inline($data['title'] ?? 'Bill of materials') }}</h2>
            @if (! empty($data['intro']))<p style="margin: 0; max-width: 30em; color: #5C5C57; font-size: 16px; line-height: 1.55">{{ $data['intro'] }}</p>@endif
        </div>
        <div class="scroll-x">
            <table class="bom">
                <thead><tr><th scope="col" style="width: 90px">Part no.</th><th scope="col" style="width: 260px">Component</th><th scope="col">What it does</th><th scope="col" style="width: 140px">From plan</th></tr></thead>
                <tbody>
                    @foreach ($data['rows'] ?? [] as $r)
                        <tr><td class="mono" style="color: #C23A00; font-size: 13px">{{ $r['no'] ?? '' }}</td><td style="font-weight: 600; font-size: 17px">{{ $r['component'] }}</td><td style="color: #3B3B37; line-height: 1.55">{{ $r['body'] ?? '' }}</td><td class="mono" style="font-size: 13px">{{ $r['plan'] ?? '' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
