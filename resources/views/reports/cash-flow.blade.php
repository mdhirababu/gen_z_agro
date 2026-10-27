@extends('layouts.farm')
@section('title', 'নগদ প্রবাহ')

@section('content')
    @php
        $money = fn($v) => \App\Support\Money::fmt($v);
        $series = $report['series'];
    @endphp

    <div class="page-head">
        <div>
            <h1>নগদ প্রবাহ</h1>
            <p>আসলে কত টাকা হাতে এলো আর কত বেরিয়ে গেল।</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('farms.reports.index', $farm) }}" class="btn btn-outline-secondary"><i
                    class="bi bi-arrow-left me-1"></i>সব রিপোর্ট</a>
            @include('reports._export', ['report' => 'cash-flow'])
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i
                    class="bi bi-printer me-1"></i>প্রিন্ট</button>
        </div>
    </div>

    <div class="d-none d-print-block mb-3"><strong>{{ $farm->name }}</strong>, নগদ প্রবাহ, {{ $period->label }}</div>

    @include('reports._period', ['route' => 'farms.reports.cash-flow'])

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kpi">
                <div class="kpi-label">নগদ আয়</div>
                <div class="kpi-value">{{ $money($report['in_total']) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi peach">
                <div class="kpi-label">নগদ ব্যয়</div>
                <div class="kpi-value">{{ $money($report['out_total']) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi {{ $report['net'] >= 0 ? 'sky' : 'rose' }}">
                <div class="kpi-label">নিট নগদ</div>
                <div class="kpi-value {{ $report['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $money($report['net']) }}</div>
            </div>
        </div>
    </div>

    <div class="panel h-auto mb-3">
        <div class="panel-head">{{ $series['granularity'] === 'day' ? 'দিনভিত্তিক' : 'মাসভিত্তিক' }} আয় ও ব্যয়</div>
        <div class="panel-body">
            @include('reports._bars', [
                'labels' => $series['labels'],
                'a' => $series['in'],
                'b' => $series['out'],
                'nameA' => 'নগদ আয়',
                'nameB' => 'নগদ ব্যয়',
            ])
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-head">কোন আয় থেকে কত এলো</div>
                <div class="panel-body py-2">
                    @forelse ($report['in_by_type'] as $row)
                        <div class="pay-row-item">
                            <span><span class="dot-key"
                                    style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span>
                            <strong>{{ $money($row['total']) }}</strong>
                        </div>
                    @empty
                        <div class="text-muted py-2">এই সময়ে কোনো টাকা আসেনি।</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-head">কোন খাতে কত গেল</div>
                <div class="panel-body py-2">
                    @forelse ($report['out_by_category'] as $row)
                        <div class="pay-row-item">
                            <span><span class="dot-key"
                                    style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span>
                            <strong>{{ $money($row['total']) }}</strong>
                        </div>
                    @empty
                        <div class="text-muted py-2">এই সময়ে কোনো টাকা যায়নি।</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="table-card mb-3">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ $series['granularity'] === 'day' ? 'তারিখ' : 'মাস' }}</th>
                        <th class="text-end">নগদ আয়</th>
                        <th class="text-end">নগদ ব্যয়</th>
                        <th class="text-end">নিট</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($series['labels'] as $i => $label)
                        @php $net = $series['in'][$i] - $series['out'][$i]; @endphp
                        @if ($series['in'][$i] > 0 || $series['out'][$i] > 0)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="text-end">{{ $money($series['in'][$i]) }}</td>
                                <td class="text-end">{{ $money($series['out'][$i]) }}</td>
                                <td class="text-end fw-semibold {{ $net >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $money($net) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>মোট</td>
                        <td class="text-end">{{ $money($report['in_total']) }}</td>
                        <td class="text-end">{{ $money($report['out_total']) }}</td>
                        <td class="text-end {{ $report['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $money($report['net']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="small text-muted">
        <ul class="mb-0">
            <li>শুধু আসলে টাকা পাওয়া বা দেওয়ার রেকর্ড ধরা হয়েছে। বকেয়া বিক্রি বা বকেয়া খরচ এতে নেই, আর বাতিল করা
                পেমেন্টও ধরা হয়নি।</li>
            <li>গরু কেনার টাকা কেনার তারিখে পুরোটা বেরিয়ে গেছে ধরা
                হয়েছে{{ $report['cow_purchase_total'] > 0 ? ' (এই সময়ে ' . $money($report['cow_purchase_total']) . ')' : '' }}।
                আগে থেকে ছিল বা খামারে জন্মানো গরুর জন্য কোনো নগদ খরচ ধরা হয় না।</li>
        </ul>
    </div>
@endsection
