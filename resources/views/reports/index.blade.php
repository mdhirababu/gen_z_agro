@extends('layouts.farm')
@section('title', 'রিপোর্ট')

@section('content')
    @php
        $money = fn($v) => \App\Support\Money::fmt($v);
        $tone = fn($v) => $v >= 0 ? 'text-success' : 'text-danger';
    @endphp

    <div class="page-head">
        <div>
            <h1>রিপোর্ট</h1>
            <p>{{ $period->label }}-এর সারসংক্ষেপ। বিস্তারিত দেখতে নিচের রিপোর্ট খুলুন।</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="ic mint"><i class="bi bi-cash-stack"></i></span>
                        <div>
                            <h5 class="mb-0">নগদ প্রবাহ</h5>
                            <div class="small text-muted">আসলে কত টাকা হাতে এলো আর কত গেল</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between py-1"><span>নগদ
                            আয়</span><strong>{{ $money($cash['in_total']) }}</strong></div>
                    <div class="d-flex justify-content-between py-1"><span>নগদ
                            ব্যয়</span><strong>{{ $money($cash['out_total']) }}</strong></div>
                    <div class="d-flex justify-content-between py-2 border-top mt-1">
                        <span class="fw-semibold">নিট নগদ</span>
                        <strong class="{{ $tone($cash['net']) }}">{{ $money($cash['net']) }}</strong>
                    </div>
                    <a href="{{ route('farms.reports.cash-flow', $farm) }}" class="btn btn-primary mt-2">নগদ প্রবাহ
                        দেখুন</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="ic peach"><i class="bi bi-graph-up-arrow"></i></span>
                        <div>
                            <h5 class="mb-0">আনুমানিক লাভ-ক্ষতি</h5>
                            <div class="small text-muted">বকেয়াসহ আয়-ব্যয়, গরুর দাম ধরে</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between py-1"><span>পরিচালন ফল (আয় − ব্যয়)</span><strong
                            class="{{ $tone($pl['operating_result']) }}">{{ $money($pl['operating_result']) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1"><span>গরু বিক্রির ফল</span><strong
                            class="{{ $tone($pl['cow_result']) }}">{{ $money($pl['cow_result']) }}</strong></div>
                    <div class="d-flex justify-content-between py-2 border-top mt-1">
                        <span class="fw-semibold">আনুমানিক নিট ফল</span>
                        <strong class="{{ $tone($pl['net']) }}">{{ $money($pl['net']) }}</strong>
                    </div>
                    <a href="{{ route('farms.reports.profit-loss', $farm) }}" class="btn btn-primary mt-2">লাভ-ক্ষতি
                        দেখুন</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="ic lilac"><i class="bi bi-collection"></i></span>
                        <div>
                            <h5 class="mb-0">গরুভিত্তিক লাভ-ক্ষতি</h5>
                            <div class="small text-muted">প্রতিটি গরুর আয়, খরচ (যৌথ খরচের ভাগসহ) ও ফল</div>
                        </div>
                    </div>
                    @if ($cowReportAllowed)
                        <a href="{{ route('farms.reports.cows', $farm) }}" class="btn btn-primary">গরুভিত্তিক রিপোর্ট দেখুন</a>
                    @else
                        <div class="small text-muted mb-2">এই রিপোর্ট আপনার বর্তমান প্ল্যানে নেই।</div>
                        @can('manageBilling', $farm)
                            <a href="{{ route('farms.billing.index', $farm) }}" class="btn btn-outline-primary">প্ল্যান
                                দেখুন</a>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-body">
                    <div class="d-flex align-items-center gap-3">
                        <span class="ic sky"><i class="bi bi-file-earmark-arrow-down"></i></span>
                        <div>
                            <h5 class="mb-0">Excel ও PDF ডাউনলোড</h5>
                            <div class="small text-muted">যেকোনো রিপোর্ট খুলে ওপরের "Excel" বা "PDF" বাটন চাপুন। যে সময়কাল
                                ও ফিল্টার বেছেছেন, সেটাই নামবে।</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="small text-muted mt-3">
        নগদ প্রবাহ আর লাভ-ক্ষতির সংখ্যা আলাদা হওয়াই স্বাভাবিক: নগদে শুধু আসলে টাকা আসা-যাওয়া ধরা হয়, লাভ-ক্ষতিতে বকেয়াও
        ধরা হয়।
    </div>
@endsection
