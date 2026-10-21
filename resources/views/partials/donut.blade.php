@php
    $total = array_sum(array_column($segments, 1)) ?: 1;
    $acc = 0;
    $stops = [];
    foreach ($segments as [$label, $value, $color]) {
        $from = round(($acc / $total) * 100, 2);
        $acc += $value;
        $to = round(($acc / $total) * 100, 2);
        $stops[] = "{$color} {$from}% {$to}%";
    }
@endphp

<div class="d-flex align-items-center gap-3 flex-wrap">
    <div class="donut" style="background: conic-gradient({{ implode(', ', $stops) }})">
        <div class="donut-center">
            <b>{{ $center }}</b>
            <small>{{ $centerLabel }}</small>
        </div>
    </div>
    <ul class="legend">
        @foreach ($segments as [$label, $value, $color])
            <li>
                <span class="dot" style="background: {{ $color }}"></span>
                {{ $label }}
                <b class="ms-auto ps-3">{{ $value }}</b>
            </li>
        @endforeach
    </ul>
</div>
