@extends('layouts.farm')
@section('title', 'আয় ও বিক্রি')

@section('content')
    @php
        $types = config('farm.sale_types');
        $total = (float) $totals->total;
        $paid = (float) $totals->paid;
    @endphp

    <div class="page-head">
        <div>
            <h1>আয় ও বিক্রি</h1>
            <p>দুধ, গরু, গোবর ও অন্যান্য আয়ের হিসাব।</p>
        </div>
        <a href="{{ route('farms.sales.create', $farm) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>নতুন বিক্রি
        </a>
    </div>

    {{-- ===== ফিল্টার ===== --}}
    <form method="GET" action="{{ route('farms.sales.index', $farm) }}" class="panel h-auto mb-3">
        <div class="panel-body py-3">
            <div class="row g-2">
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1">মাস</label>
                    <select name="m" class="form-select" onchange="this.form.submit()">
                        @foreach ($months as $m)
                            <option value="{{ $m }}" @selected($month === $m)>
                                {{ substr($m, 5) }}/{{ substr($m, 0, 4) }}</option>
                        @endforeach
                        <option value="all" @selected($month === 'all')>সব সময়</option>
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small mb-1">ধরন</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">সব ধরন</option>
                        @foreach ($types as $key => $meta)
                            <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small mb-1">ক্রেতা</label>
                    <select name="customer" class="form-select" onchange="this.form.submit()">
                        <option value="">সবাই</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected($filters['customerId'] === (string) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1">আদায়</label>
                    <select name="pay" class="form-select" onchange="this.form.submit()">
                        <option value="">সব</option>
                        @foreach (config('farm.payment_statuses') as $key => $label)
                            <option value="{{ $key }}" @selected($filters['pay'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2">
                    <label class="form-label small mb-1">দেখান</label>
                    <select name="state" class="form-select" onchange="this.form.submit()">
                        <option value="active" @selected($filters['state'] === 'active')>চালু বিক্রি</option>
                        <option value="void" @selected($filters['state'] === 'void')>বাতিল বিক্রি</option>
                    </select>
                </div>
            </div>
        </div>
    </form>

    {{-- ===== সারসংক্ষেপ ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kpi sky">
                <div class="kpi-label">{{ $filters['state'] === 'void' ? 'বাতিল বিক্রির মোট' : 'মোট বিক্রি' }}</div>
                <div class="kpi-value">{{ \App\Support\Money::fmt($total) }}</div>
                <div class="kpi-note">{{ $totals->cnt }}টি এন্ট্রি</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi">
                <div class="kpi-label">আদায় হয়েছে</div>
                <div class="kpi-value">{{ \App\Support\Money::fmt($paid) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi peach">
                <div class="kpi-label">এখনো পাওনা</div>
                <div class="kpi-value">
                    {{ \App\Support\Money::fmt($filters['state'] === 'void' ? 0 : max(0, $total - $paid)) }}</div>
            </div>
        </div>
    </div>

    {{-- ===== তালিকা ===== --}}
    @if ($sales->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-cash-stack"></i>
                <h2>কোনো বিক্রি পাওয়া যায়নি</h2>
                <p>ফিল্টার বদলে দেখুন, অথবা নতুন বিক্রি নথিভুক্ত করুন।</p>
                <a href="{{ route('farms.sales.create', $farm) }}" class="btn btn-primary">নতুন বিক্রি যোগ করুন</a>
            </div>
        </div>
    @else
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>তারিখ</th>
                            <th>বিক্রি</th>
                            <th>ক্রেতা</th>
                            <th class="text-end">টাকা</th>
                            <th>আদায়</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $s)
                            @php $meta = $s->typeMeta(); @endphp
                            <tr class="{{ $s->isVoid() ? 'is-void' : '' }}">
                                <td class="text-nowrap">{{ $s->sold_at->format('d/m/Y') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="ic sm {{ $meta['tone'] }}"><i
                                                class="bi {{ $meta['icon'] }}"></i></span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold">{{ $meta['label'] }}</div>
                                            <div class="small text-muted text-truncate" style="max-width: 16rem">
                                                @if ((float) $s->quantity > 0)
                                                    {{ rtrim(rtrim(number_format((float) $s->quantity, 2), '0'), '.') }}
                                                    {{ $s->unit }}
                                                @else
                                                    {{ $s->description ?: '—' }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($s->customer)
                                        @if (!$s->customer->trashed())
                                            <a
                                                href="{{ route('farms.customers.show', [$farm, $s->customer]) }}">{{ $s->customer->name }}</a>
                                        @else
                                            {{ $s->customer->name }}
                                        @endif
                                    @else
                                        <span class="small text-muted">নগদ বিক্রি</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold text-nowrap amt">
                                    {{ \App\Support\Money::fmt($s->total_amount) }}</td>
                                <td>
                                    @if ($s->isVoid())
                                        <span class="pill gray">বাতিল</span>
                                    @else
                                        <span class="pill {{ $s->paymentPill() }}">{{ $s->paymentLabel() }}</span>
                                        @if ($s->payment_status === 'partial')
                                            <div class="small text-muted">বাকি
                                                {{ \App\Support\Money::fmt($s->dueAmount()) }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('farms.sales.show', [$farm, $s]) }}" class="icon-btn sm"
                                        title="বিস্তারিত দেখুন" aria-label="বিস্তারিত দেখুন">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $sales->links() }}</div>
    @endif
@endsection
