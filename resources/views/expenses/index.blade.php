@extends('layouts.farm')
@section('title', 'খরচ')

@section('content')
    @php
        $cats = config('farm.expense_categories');
        $total = (float) $totals->total;
        $paid = (float) $totals->paid;
    @endphp

    <div class="page-head">
        <div>
            <h1>খরচ</h1>
            <p>খামারের সব খরচ, কে কত টাকা পাবে সহ।</p>
        </div>
        <a href="{{ route('farms.expenses.create', $farm) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>নতুন খরচ
        </a>
    </div>

    {{-- ===== ফিল্টার ===== --}}
    <form method="GET" action="{{ route('farms.expenses.index', $farm) }}" class="panel h-auto mb-3">
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
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">সব ধরন</option>
                        @foreach ($cats as $key => $meta)
                            <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="form-label small mb-1">গরু</label>
                    <select name="cow" class="form-select" onchange="this.form.submit()">
                        <option value="">সব</option>
                        @foreach ($cows as $c)
                            <option value="{{ $c->id }}" @selected($filters['cowId'] === (string) $c->id)>{{ $c->tag_number }}
                                @if ($c->name)
                                    ({{ $c->name }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1">পেমেন্ট</label>
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
                        <option value="active" @selected($filters['state'] === 'active')>চালু খরচ</option>
                        <option value="void" @selected($filters['state'] === 'void')>বাতিল খরচ</option>
                    </select>
                </div>
            </div>
        </div>
    </form>

    {{-- ===== সারসংক্ষেপ ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kpi peach">
                <div class="kpi-label">{{ $filters['state'] === 'void' ? 'বাতিল খরচের মোট' : 'মোট খরচ' }}</div>
                <div class="kpi-value">{{ \App\Support\Money::fmt($total) }}</div>
                <div class="kpi-note">{{ $totals->cnt }}টি এন্ট্রি</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi">
                <div class="kpi-label">পরিশোধ হয়েছে</div>
                <div class="kpi-value">{{ \App\Support\Money::fmt($paid) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi rose">
                <div class="kpi-label">এখনো বকেয়া</div>
                <div class="kpi-value">
                    {{ \App\Support\Money::fmt($filters['state'] === 'void' ? 0 : max(0, $total - $paid)) }}</div>
            </div>
        </div>
    </div>

    {{-- ===== তালিকা ===== --}}
    @if ($expenses->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-receipt"></i>
                <h2>কোনো খরচ পাওয়া যায়নি</h2>
                <p>ফিল্টার বদলে দেখুন, অথবা নতুন খরচ যোগ করুন।</p>
                <a href="{{ route('farms.expenses.create', $farm) }}" class="btn btn-primary">নতুন খরচ যোগ করুন</a>
            </div>
        </div>
    @else
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>তারিখ</th>
                            <th>খরচ</th>
                            <th>গরু</th>
                            <th class="text-end">টাকা</th>
                            <th>পেমেন্ট</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $e)
                            @php $meta = $e->categoryMeta(); @endphp
                            <tr class="{{ $e->isVoid() ? 'is-void' : '' }}">
                                <td class="text-nowrap">{{ $e->expense_date->format('d/m/Y') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="ic sm {{ $meta['tone'] }}"><i
                                                class="bi {{ $meta['icon'] }}"></i></span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold">{{ $meta['label'] }}</div>
                                            <div class="small text-muted text-truncate" style="max-width: 16rem">
                                                {{ $e->description ?: ($e->vendor_name ?: '—') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($e->cow && !$e->cow->trashed())
                                        <a
                                            href="{{ route('farms.cows.show', [$farm, $e->cow]) }}">{{ $e->cow->tag_number }}</a>
                                    @elseif ($e->cow)
                                        {{ $e->cow->tag_number }}
                                    @else
                                        <span class="small text-muted">সাধারণ</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold text-nowrap amt">
                                    {{ \App\Support\Money::fmt($e->total_amount) }}</td>
                                <td>
                                    @if ($e->isVoid())
                                        <span class="pill gray">বাতিল</span>
                                    @else
                                        <span class="pill {{ $e->paymentPill() }}">{{ $e->paymentLabel() }}</span>
                                        @if ($e->dueAmount() > 0 && $e->payment_status === 'partial')
                                            <div class="small text-muted">বাকি
                                                {{ \App\Support\Money::fmt($e->dueAmount()) }}</div>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('farms.expenses.show', [$farm, $e]) }}" class="icon-btn sm"
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

        <div class="mt-3">{{ $expenses->links() }}</div>
    @endif
@endsection
