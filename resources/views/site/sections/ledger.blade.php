<section class="sec">
    <div class="wrap ledger">
        <div class="l">
            <div class="k" style="margin-bottom: 16px">{{ $data['before_label'] ?? '§1 Without Composa' }}</div>
            @foreach ($data['before'] ?? [] as $i => $b)
                <div class="row"><span class="num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><s>{{ $b['text'] }}</s></div>
            @endforeach
        </div>
        <div class="r">
            <div class="k" style="margin-bottom: 16px; color: #C23A00">{{ $data['after_label'] ?? '§2 With Composa' }}</div>
            @foreach ($data['after'] ?? [] as $i => $a)
                <div class="row"><span class="num" style="color: #C23A00">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span><span><strong style="font-weight: 600">{{ $a['title'] }}</strong> <span style="color: #5C5C57">{{ $a['text'] ?? '' }}</span></span></div>
            @endforeach
        </div>
    </div>
</section>
