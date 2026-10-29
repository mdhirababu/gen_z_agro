@extends('layouts.farm')
@section('title', 'ড্যাশবোর্ড')

@section('content')
    @php
        $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.') ?: '0';
        $money = fn($v) => \App\Support\Money::fmt($v);

        $heroStats = [
            ['milking', 'mint', 'bi-droplet-half'],
            ['dry', 'sand', 'bi-moon-stars'],
            ['heifer', 'lilac', 'bi-heart-pulse'],
            ['calf', 'peach', 'bi-collection'],
        ];
        $milkMax = max((float) $milkWeek->max('liters'), 1);

        $cowSegments = [];
        foreach (config('farm.categories') as $key => $meta) {
            if (($cowCounts[$key] ?? 0) > 0) {
                $cowSegments[] = [$meta['label'], (int) $cowCounts[$key], $meta['color']];
            }
        }
    @endphp

    {{-- ===== গ্রিটিং ব্যানার ===== --}}
    <section class="hero mb-3">
        <h1>স্বাগতম, {{ auth()->user()->name }}</h1>
        <p>{{ $farm->name }}: খামারে মোট {{ $cowTotal }}টি সক্রিয় গরু ও বাছুর</p>

        <div class="hero-strip">
            @foreach ($heroStats as [$key, $tone, $icon])
                <div class="mini-stat">
                    <span class="ic {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                    <div>
                        <b>{{ $cowCounts[$key] ?? 0 }}</b>
                        <small>{{ config('farm.categories')[$key]['label'] }}</small>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @include('dashboard._onboarding')

    {{-- ===== KPI ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-6 {{ $canFinance ? 'col-xl-3' : 'col-xl-6' }}">
            <div class="kpi sky">
                <div class="kpi-label">আজকের দুধ</div>
                <div class="kpi-value">{{ $fmt($milkToday) }} লিটার</div>
                <div class="kpi-note">গত ৭ দিনের দৈনিক গড় {{ $fmt($milkAvg) }} লিটার</div>
            </div>
        </div>

        @if ($canFinance)
            <div class="col-6 col-xl-3">
                <div class="kpi">
                    <div class="kpi-label">এই মাসের বিক্রি</div>
                    <div class="kpi-value">{{ $money($monthSales) }}</div>
                    <div class="kpi-note">দুধ, গরু ও অন্যান্য আয়</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="kpi peach">
                    <div class="kpi-label">এই মাসের খরচ</div>
                    <div class="kpi-value">{{ $money($monthExpenses) }}</div>
                    <div class="kpi-note">বিক্রি ও খরচের পার্থক্য: {{ $money($monthSales - $monthExpenses) }}</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="kpi lilac">
                    <div class="kpi-label">মোট পাওনা</div>
                    <div class="kpi-value">{{ $money($receivable) }}</div>
                    <div class="kpi-note">ক্রেতাদের কাছে বকেয়া</div>
                </div>
            </div>
        @else
            <div class="col-6 col-xl-6">
                <div class="kpi">
                    <div class="kpi-label">সক্রিয় গরু ও বাছুর</div>
                    <div class="kpi-value">{{ $cowTotal }}টি</div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===== দুধের সাতদিন ও গরুর অবস্থা ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-head d-flex justify-content-between align-items-center">
                    <span>গত ৭ দিনের দুধ (লিটার)</span>
                    <a href="{{ route('farms.milk.index', $farm) }}" class="small fw-normal">বিস্তারিত</a>
                </div>
                <div class="panel-body">
                    <div class="bars">
                        @foreach ($milkWeek as $day)
                            <div class="bar {{ $loop->last ? 'is-today' : '' }}">
                                <span class="bar-val">{{ $day['liters'] > 0 ? $fmt($day['liters']) : '' }}</span>
                                <div class="bar-track">
                                    <div class="bar-fill" style="height: {{ round(($day['liters'] / $milkMax) * 100) }}%">
                                    </div>
                                </div>
                                <span class="bar-label">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($milkWeek->sum('liters') == 0)
                        <div class="small text-muted mt-2">
                            এখনো কোনো দুধের এন্ট্রি নেই।
                            <a href="{{ route('farms.milk.entry', $farm) }}">আজকের দুধ লিখুন</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-head">গরুর অবস্থা</div>
                <div class="panel-body">
                    @if ($cowTotal > 0)
                        @include('partials.donut', [
                            'segments' => $cowSegments,
                            'center' => $cowTotal,
                            'centerLabel' => 'মোট',
                        ])
                    @else
                        <div class="empty-state py-4">
                            <i class="bi bi-collection"></i>
                            <p class="mb-2 mt-2">এখনো কোনো গরু যোগ করা হয়নি।</p>
                            <a href="{{ route('farms.cows.create', $farm) }}" class="btn btn-sm btn-primary">গরু যোগ
                                করুন</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($canFinance)
        @php
            $hasChart = $chart && max(array_merge($chart['sales'], $chart['expenses'])) > 0;
            $w = 560;
            $h = 210;
            $padL = 44;
            $padB = 26;
            $padT = 12;
            $padR = 10;

            if ($hasChart) {
                $rawMax = max(array_merge($chart['sales'], $chart['expenses']));
                $pow = 10 ** floor(log10($rawMax));
                $niceMax = (ceil(($rawMax / $pow) * 2) / 2) * $pow;
                $n = count($chart['labels']);
                $pt = fn(array $vals) => implode(
                    ' ',
                    array_map(
                        fn($v, $i) => round($padL + $i * (($w - $padL - $padR) / ($n - 1)), 1) .
                            ',' .
                            round($padT + ($h - $padT - $padB) * (1 - $v / $niceMax), 1),
                        $vals,
                        array_keys($vals),
                    ),
                );
                $tickLabel = fn($t) => $t >= 1000
                    ? rtrim(rtrim(number_format($t / 1000, 1), '0'), '.') . 'k'
                    : (string) round($t);
            }
        @endphp

        {{-- ===== আয়-খরচের চার্ট ও খরচের ভাগ ===== --}}
        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="panel h-100">
                    <div class="panel-head d-flex justify-content-between align-items-center">
                        <span>বিক্রি ও খরচ, গত ১২ মাস (টাকা)</span>
                        <span class="small fw-normal text-muted">
                            <span class="dot-key" style="background:#1f8a4f"></span>বিক্রি
                            <span class="dot-key ms-3" style="background:#f0a04b"></span>খরচ
                        </span>
                    </div>
                    <div class="panel-body">
                        @if ($hasChart)
                            <svg class="chart" viewBox="0 0 {{ $w }} {{ $h }}" role="img"
                                aria-label="মাসভিত্তিক বিক্রি ও খরচের লাইন চার্ট">
                                @foreach ([0, 0.25, 0.5, 0.75, 1] as $f)
                                    @php
                                        $t = $niceMax * $f;
                                        $ty = $padT + ($h - $padT - $padB) * (1 - $f);
                                    @endphp
                                    <line class="grid" x1="{{ $padL }}" x2="{{ $w - $padR }}"
                                        y1="{{ $ty }}" y2="{{ $ty }}" />
                                    <text class="axis" x="{{ $padL - 6 }}" y="{{ $ty + 4 }}"
                                        text-anchor="end">{{ $tickLabel($t) }}</text>
                                @endforeach
                                @foreach ($chart['labels'] as $i => $label)
                                    <text class="axis" x="{{ $padL + $i * (($w - $padL - $padR) / ($n - 1)) }}"
                                        y="{{ $h - 6 }}" text-anchor="middle">{{ $label }}</text>
                                @endforeach
                                <polyline class="line line-rev" points="{{ $pt($chart['sales']) }}" />
                                <polyline class="line line-exp" points="{{ $pt($chart['expenses']) }}" />
                            </svg>
                            <div class="small text-muted mt-2">খরচ ও বিক্রির পার্থক্য নিট লাভ নয়। যৌথ খরচ ও গরুর মূল্য ধরে
                                লাভ-ক্ষতি রিপোর্টে আসবে।</div>
                        @else
                            <div class="empty-state py-4">
                                <i class="bi bi-graph-up"></i>
                                <p class="mb-0 mt-2">বিক্রি ও খরচ নথিভুক্ত করলে এখানে চার্ট দেখা যাবে।</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="panel h-100">
                    <div class="panel-head">এই মাসের খরচের ভাগ</div>
                    <div class="panel-body">
                        @if (count($expenseSegments) > 0)
                            @include('partials.donut', [
                                'segments' => $expenseSegments,
                                'center' => $money($monthExpenses),
                                'centerLabel' => 'এ মাসে',
                            ])
                        @else
                            <div class="empty-state py-4">
                                <i class="bi bi-receipt"></i>
                                <p class="mb-2 mt-2">এই মাসে কোনো খরচ নেই।</p>
                                <a href="{{ route('farms.expenses.create', $farm) }}" class="btn btn-sm btn-primary">খরচ
                                    যোগ করুন</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== আসন্ন কাজ ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-head d-flex justify-content-between align-items-center">
                    <span>আসন্ন কাজ</span>
                    <a href="{{ route('farms.health.tasks', $farm) }}" class="small fw-normal">সব দেখুন</a>
                </div>
                <div class="panel-body">
                    @if ($tasks->isEmpty())
                        <div class="text-muted"><i class="bi bi-check2-circle text-success me-1"></i>আগামী ৩০ দিনে কোনো
                            টিকা বা পরীক্ষা বাকি নেই।</div>
                    @else
                        @if ($overdueCount > 0)
                            <div class="alert alert-danger py-2 mb-2">{{ $overdueCount }}টি কাজের সময় পেরিয়ে গেছে।</div>
                        @endif
                        @foreach ($tasks as $t)
                            @php $d = $t->days(); @endphp
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span>
                                    <a
                                        href="{{ route('farms.cows.show', [$farm, $t->cow]) }}">{{ $t->cow->tag_number }}</a>,
                                    {{ $t->title }}
                                </span>
                                <span class="pill {{ $d < 0 ? 'red' : ($d <= 7 ? 'orange' : 'green') }}">
                                    {{ $d < 0 ? abs($d) . ' দিন দেরি' : ($d === 0 ? 'আজ' : $d . ' দিন পরে') }}
                                </span>
                            </div>
                        @endforeach
                        @if ($taskCount > $tasks->count())
                            <div class="small text-muted mt-1">আরও {{ $taskCount - $tasks->count() }}টি কাজ আছে।</div>
                        @endif
                    @endif

                    @if ($sickCount > 0)
                        <div class="mt-3 pt-3 border-top small">
                            চিকিৎসাধীন গরু:
                            <a
                                href="{{ route('farms.health.index', [$farm, 'ongoing' => 1]) }}">{{ $sickCount }}টি</a>
                        </div>
                    @endif
                    @if ($pregnantCount > 0)
                        <div class="mt-2 small">
                            গর্ভবতী গরু:
                            <a
                                href="{{ route('farms.cows.index', [$farm, 'category' => 'pregnant']) }}">{{ $pregnantCount }}টি</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== মনোযোগ দরকার ও শুরুর তালিকা ===== --}}
    <div class="row g-3">
        @if ($canFinance)
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-head">মনোযোগ দরকার</div>
                    <div class="panel-body">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>পেতে হবে (ক্রেতাদের কাছে)</span>
                            <strong>{{ $money($receivable) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span>দিতে হবে (বকেয়া খরচ)</span>
                            <strong>{{ $money($payable) }}</strong>
                        </div>

                        @if ($topDues->isNotEmpty())
                            <div class="small text-muted mt-3 mb-1">সবচেয়ে বেশি বকেয়া</div>
                            @foreach ($topDues as $row)
                                <div class="d-flex justify-content-between py-1">
                                    <a
                                        href="{{ route('farms.customers.show', [$farm, $row['customer']]) }}">{{ $row['customer']->name }}</a>
                                    <span class="pill red">{{ $money($row['due']) }}</span>
                                </div>
                            @endforeach
                        @endif

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a href="{{ route('farms.customers.index', $farm) }}"
                                class="btn btn-sm btn-outline-primary">ক্রেতা ও বকেয়া</a>
                            <a href="{{ route('farms.expenses.index', [$farm, 'm' => 'all']) }}"
                                class="btn btn-sm btn-outline-secondary">সব খরচ</a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        {{-- @if ($checklist->isNotEmpty() && !$checklistDone)
            <div class="col-lg-6">
                <div class="panel h-100">
                    <div class="panel-head">শুরু করুন</div>
                    <div class="panel-body">
                        <ul class="todo-list">
                            @foreach ($checklist as $item)
                                <li class="{{ $item['done'] ? 'is-done' : '' }}">
                                    <i class="bi {{ $item['done'] ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif --}}
    </div>
@endsection
