@extends('layouts.farm')
@section('title', 'লাভ-ক্ষতি')

@section('content')
    @php
        $money = fn($v) => \App\Support\Money::fmt($v);
        $tone = fn($v) => $v >= 0 ? 'text-success' : 'text-danger';
        $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.') ?: '0';
        $saleTypes = config('farm.sale_types');

        $expenseSegments = collect($report['expenses'])
            ->map(fn($e) => [$e['label'], (int) round($e['total']), $e['color']])
            ->filter(fn($s) => $s[1] > 0)
            ->values()
            ->all();

        $incomeSegments = [];
        foreach ($saleTypes as $key => $meta) {
            if (($report['revenue'][$key] ?? 0) > 0) {
                $incomeSegments[] = [$meta['label'], (int) round($report['revenue'][$key]), $meta['color']];
            }
        }
        $writeOffs = $report['write_offs'];
    @endphp

    <div class="page-head">
        <div>
            <h1>আনুমানিক লাভ-ক্ষতি</h1>
            <p>বকেয়াসহ আয় ও ব্যয়, আর গরুর দাম ধরে।</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('farms.reports.index', $farm) }}" class="btn btn-outline-secondary"><i
                    class="bi bi-arrow-left me-1"></i>সব রিপোর্ট</a>
            @include('reports._export', ['report' => 'profit-loss'])
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i
                    class="bi bi-printer me-1"></i>প্রিন্ট</button>
        </div>
    </div>

    <div class="d-none d-print-block mb-3"><strong>{{ $farm->name }}</strong>, লাভ-ক্ষতি, {{ $period->label }}</div>

    @include('reports._period', ['route' => 'farms.reports.profit-loss'])

    {{-- ===== ফলাফল ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kpi sky">
                <div class="kpi-label">পরিচালন ফল (আয় − ব্যয়)</div>
                <div class="kpi-value {{ $tone($report['operating_result']) }}">{{ $money($report['operating_result']) }}
                </div>
                <div class="kpi-note">গরু বিক্রি বাদে</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi peach">
                <div class="kpi-label">গরু বিক্রির ফল</div>
                <div class="kpi-value {{ $tone($report['cow_result']) }}">{{ $money($report['cow_result']) }}</div>
                <div class="kpi-note">বিক্রির টাকা − গরুর ভিত্তিমূল্য</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi {{ $report['net'] >= 0 ? '' : 'rose' }}">
                <div class="kpi-label">আনুমানিক নিট ফল</div>
                <div class="kpi-value {{ $tone($report['net']) }}">{{ $money($report['net']) }}</div>
                <div class="kpi-note">{{ $report['net'] >= 0 ? 'লাভ' : 'ক্ষতি' }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- ===== আয় ===== --}}
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-head">আয়</div>
                <div class="panel-body">
                    @if (count($incomeSegments) > 0)
                        @include('partials.donut', [
                            'segments' => $incomeSegments,
                            'center' => $money($report['operating_income'] + $report['revenue']['cow']),
                            'centerLabel' => 'মোট আয়',
                        ])
                    @endif

                    <div class="mt-3">
                        @foreach ($saleTypes as $key => $meta)
                            <div class="d-flex justify-content-between py-1">
                                <span><span class="dot-key"
                                        style="background: {{ $meta['color'] }}"></span>{{ $meta['label'] }}</span>
                                <strong>{{ $money($report['revenue'][$key]) }}</strong>
                            </div>
                        @endforeach
                        <div class="d-flex justify-content-between py-2 border-top mt-1">
                            <span>পরিচালন আয় (গরু বিক্রি বাদে)</span>
                            <strong>{{ $money($report['operating_income']) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== ব্যয় ===== --}}
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-head">ব্যয়</div>
                <div class="panel-body">
                    @if (count($expenseSegments) > 0)
                        @include('partials.donut', [
                            'segments' => $expenseSegments,
                            'center' => $money($report['operating_expense']),
                            'centerLabel' => 'মোট ব্যয়',
                        ])
                        <div class="mt-3">
                            @foreach ($report['expenses'] as $e)
                                <div class="d-flex justify-content-between py-1">
                                    <span><span class="dot-key"
                                            style="background: {{ $e['color'] }}"></span>{{ $e['label'] }}</span>
                                    <strong>{{ $money($e['total']) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted">এই সময়ে কোনো খরচ নেই।</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== গরু বিক্রির ফল ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">গরু বিক্রির ফল</div>
        <div class="panel-body">
            @if (count($report['cow_lines']) > 0)
                <div class="table-responsive">
                    <table class="table align-middle mb-2">
                        <thead>
                            <tr>
                                <th>গরু</th>
                                <th>তারিখ</th>
                                <th class="text-end">বিক্রির টাকা</th>
                                <th class="text-end">ভিত্তিমূল্য</th>
                                <th class="text-end">ফল</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['cow_lines'] as $line)
                                <tr>
                                    <td><a
                                            href="{{ route('farms.sales.show', [$farm, $line['id']]) }}">{{ $line['tag'] }}</a>
                                    </td>
                                    <td class="text-nowrap">{{ $line['sold_at']->format('d/m/Y') }}</td>
                                    <td class="text-end">{{ $money($line['price']) }}</td>
                                    <td class="text-end">{{ $money($line['base']) }}</td>
                                    <td class="text-end fw-semibold {{ $tone($line['result']) }}">
                                        {{ $money($line['result']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="2">মোট</td>
                                <td class="text-end">{{ $money($report['cow_sales_total']) }}</td>
                                <td class="text-end">{{ $money($report['cow_base_total']) }}</td>
                                <td class="text-end {{ $tone($report['cow_result']) }}">
                                    {{ $money($report['cow_result']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="small text-muted">ভিত্তিমূল্য: কেনা গরুর ক্রয়মূল্য, আগে থেকে থাকা গরুর প্রারম্ভিক মূল্য, খামারে
                    জন্মানো গরুর ০।</div>
            @else
                <div class="text-muted">এই সময়ে কোনো গরু বিক্রি হয়নি।</div>
            @endif
        </div>
    </div>

    {{-- ===== গরু বাদ পড়ার ক্ষতি ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">গরু বাদ পড়ায় ক্ষতি</div>
        <div class="panel-body">
            @if (count($writeOffs['lines']) > 0)
                @foreach ($writeOffs['lines'] as $line)
                    <div class="d-flex justify-content-between py-1">
                        <span>
                            <a href="{{ route('farms.cows.show', [$farm, $line['id']]) }}">{{ $line['tag'] }}</a>,
                            {{ $line['status'] === 'dead' ? 'মারা গেছে' : 'অন্যত্র সরানো হয়েছে' }},
                            {{ $line['exit_date']->format('d/m/Y') }}
                        </span>
                        <strong>{{ $money($line['base']) }}</strong>
                    </div>
                @endforeach
                <div class="d-flex justify-content-between py-2 border-top mt-1">
                    <span>মোট (মৃত {{ $money($writeOffs['dead']) }}, সরানো {{ $money($writeOffs['transferred']) }})</span>
                    <strong class="text-danger">{{ $money($writeOffs['total']) }}</strong>
                </div>
            @else
                <div class="text-muted">এই সময়ে ভিত্তিমূল্যসহ কোনো গরু মারা যায়নি বা সরানো হয়নি।</div>
            @endif
        </div>
    </div>

    {{-- ===== দুধ ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">দুধ: উৎপাদন বনাম বিক্রি</div>
        <div class="panel-body">
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <div class="tile mint">
                        <div class="tile-label">উৎপাদন</div>
                        <div class="tile-value">{{ $fmt($report['milk']['produced']) }} লি.</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="tile sky">
                        <div class="tile-label">বিক্রি</div>
                        <div class="tile-value">{{ $fmt($report['milk']['sold']) }} লি.</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="tile peach">
                        <div class="tile-label">দুধ বিক্রির আয়</div>
                        <div class="tile-value">{{ $money($report['milk']['revenue']) }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="tile lilac">
                        <div class="tile-label">গড় দর</div>
                        <div class="tile-value">
                            {{ $report['milk']['avg_price'] !== null ? $money($report['milk']['avg_price']) . '/লি.' : '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== নিট ফলের হিসাব ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">নিট ফল কীভাবে এলো</div>
        <div class="panel-body">
            <div class="d-flex justify-content-between py-1"><span>পরিচালন
                    আয়</span><span>{{ $money($report['operating_income']) }}</span></div>
            <div class="d-flex justify-content-between py-1"><span>পরিচালন ব্যয়</span><span>−
                    {{ $money($report['operating_expense']) }}</span></div>
            <div class="d-flex justify-content-between py-1 border-top"><span class="fw-semibold">পরিচালন ফল</span><strong
                    class="{{ $tone($report['operating_result']) }}">{{ $money($report['operating_result']) }}</strong>
            </div>
            <div class="d-flex justify-content-between py-1"><span>গরু বিক্রির ফল</span><span
                    class="{{ $tone($report['cow_result']) }}">{{ $report['cow_result'] >= 0 ? '+' : '−' }}
                    {{ $money(abs($report['cow_result'])) }}</span></div>
            <div class="d-flex justify-content-between py-1"><span>গরু বাদ পড়ায় ক্ষতি</span><span>−
                    {{ $money($writeOffs['total']) }}</span></div>
            <div class="d-flex justify-content-between py-2 border-top mt-1">
                <span class="fw-semibold">আনুমানিক নিট ফল</span>
                <strong class="fs-5 {{ $tone($report['net']) }}">{{ $money($report['net']) }}</strong>
            </div>
        </div>
    </div>

    <div class="small text-muted">
        <ul class="mb-0">
            <li>এটা <strong>আনুমানিক</strong> হিসাব, আইনি হিসাবরক্ষণ নয়। বকেয়া বিক্রি ও বকেয়া খরচও ধরা হয়েছে, টাকা আসুক
                বা না আসুক।</li>
            <li>খামারে থাকা (অবিক্রীত) গরুর বর্তমান বাজারদর বা বাড়তি মূল্য ধরা নেই। গরু কেনার টাকা খরচ নয়, বিক্রি হলে
                ভিত্তিমূল্য হিসেবে বাদ যায়।</li>
            <li>বাতিল করা বিক্রি বা খরচ এতে ধরা হয়নি। কোনো গরুর নামের খরচ (খাবার, চিকিৎসা) ঘটার সময়েই ব্যয়, গরুর
                ভিত্তিমূল্যের সঙ্গে আলাদা করে জোড়া হয়নি।</li>
        </ul>
    </div>
@endsection
