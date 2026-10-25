@php
    $n = count($labels);
    $w = 640;
    $h = 230;
    $padL = 48;
    $padB = 28;
    $padT = 10;
    $padR = 8;
    $max = max(array_merge($a, $b, [0]));
    $has = $max > 0;

    if ($has) {
        $pow = 10 ** floor(log10($max));
        $niceMax = (ceil(($max / $pow) * 2) / 2) * $pow;
        $groupW = ($w - $padL - $padR) / max($n, 1);
        $barW = min(20, $groupW * 0.36);
        $plotH = $h - $padT - $padB;
        $yOf = fn($v) => $padT + $plotH * (1 - $v / $niceMax);
        $tick = fn($t) => $t >= 1000 ? rtrim(rtrim(number_format($t / 1000, 1), '0'), '.') . 'k' : (string) round($t);
        $step = max(1, (int) ceil($n / 12));
    }
@endphp

@if ($has)
    <svg class="chart" viewBox="0 0 {{ $w }} {{ $h }}" role="img"
        aria-label="{{ $nameA }} ও {{ $nameB }}">
        @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
            @php $ty = $padT + $plotH * (1 - $f); @endphp
            <line class="grid" x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $ty }}"
                y2="{{ $ty }}" />
            <text class="axis" x="{{ $padL - 6 }}" y="{{ $ty + 4 }}"
                text-anchor="end">{{ $tick($niceMax * $f) }}</text>
        @endforeach

        @foreach ($labels as $i => $label)
            @php $cx = $padL + $i * $groupW + $groupW / 2; @endphp

            @if ($a[$i] > 0)
                <rect x="{{ round($cx - $barW - 1, 1) }}" y="{{ round($yOf($a[$i]), 1) }}"
                    width="{{ round($barW, 1) }}" height="{{ round($padT + $plotH - $yOf($a[$i]), 1) }}"
                    rx="2" fill="#1f8a4f" />
            @endif
            @if ($b[$i] > 0)
                <rect x="{{ round($cx + 1, 1) }}" y="{{ round($yOf($b[$i]), 1) }}" width="{{ round($barW, 1) }}"
                    height="{{ round($padT + $plotH - $yOf($b[$i]), 1) }}" rx="2" fill="#f0a04b" />
            @endif
            @if ($i % $step === 0)
                <text class="axis" x="{{ round($cx, 1) }}" y="{{ $h - 8 }}"
                    text-anchor="middle">{{ $label }}</text>
            @endif
        @endforeach
    </svg>

    <div class="small text-muted mt-2">
        <span class="dot-key" style="background:#1f8a4f"></span>{{ $nameA }}
        <span class="dot-key ms-3" style="background:#f0a04b"></span>{{ $nameB }}
    </div>
@else
    <div class="empty-state py-4">
        <i class="bi bi-bar-chart"></i>
        <p class="mb-0 mt-2">এই সময়ে কোনো টাকা আসা-যাওয়া নেই।</p>
    </div>
@endif
