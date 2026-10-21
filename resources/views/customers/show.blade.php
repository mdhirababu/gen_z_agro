@extends('layouts.farm')
@section('title', $customer->name)

@section('content')
    @php
        $due = max(0, round($total - $paid, 2));
        $pillColors = ['unpaid' => 'red', 'partial' => 'orange', 'paid' => 'green'];
    @endphp

    <div class="page-head">
        <div class="d-flex align-items-center gap-3">
            <span class="farm-avatar"
                style="width: 3rem; height: 3rem; border-radius: 1rem">{{ mb_substr($customer->name, 0, 1) }}</span>
            <div>
                <h1>{{ $customer->name }}</h1>
                <p>{{ $customer->phone ?: 'ফোন নেই' }}@if ($customer->address)
                        , {{ $customer->address }}
                    @endif
                </p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('farms.customers.index', $farm) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>ক্রেতার তালিকা
            </a>
            <a href="{{ route('farms.sales.create', $farm) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>নতুন বিক্রি
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kpi sky">
                <div class="kpi-label">মোট বিক্রি</div>
                <div class="kpi-value">{{ \App\Support\Money::fmt($total) }}</div>
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
                <div class="kpi-value">{{ \App\Support\Money::fmt($due) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="panel h-auto">
                <div class="panel-head">টাকা আদায় করুন</div>
                <div class="panel-body">
                    @if ($due > 0)
                        <form method="POST" action="{{ route('farms.customers.receive', [$farm, $customer]) }}">
                            @csrf
                            <div class="field">
                                <label for="amount" class="form-label">কত টাকা পেলেন</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input id="amount" type="number" step="0.01" min="0.01"
                                        max="{{ $due }}" name="amount" value="{{ old('amount', $due) }}"
                                        class="form-control @error('amount') is-invalid @enderror" required>
                                </div>
                                @error('amount')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="field">
                                        <label for="paid_at" class="form-label">তারিখ</label>
                                        <input id="paid_at" type="date" name="paid_at"
                                            value="{{ old('paid_at', now()->toDateString()) }}"
                                            max="{{ now()->toDateString() }}"
                                            class="form-control @error('paid_at') is-invalid @enderror" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="field">
                                        <label for="method" class="form-label">মাধ্যম</label>
                                        <select id="method" name="method" class="form-select">
                                            @foreach (config('farm.payment_methods') as $key => $label)
                                                <option value="{{ $key }}" @selected(old('method', 'cash') === $key)>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="field">
                                <label for="note" class="form-label">নোট (ঐচ্ছিক)</label>
                                <input id="note" type="text" name="note" value="{{ old('note') }}"
                                    class="form-control">
                            </div>
                            <div class="small text-muted mb-3">টাকাটা সবচেয়ে পুরোনো বিক্রির বকেয়া থেকে আগে মেটানো হবে।
                            </div>
                            <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>আদায় নথিভুক্ত
                                করুন</button>
                        </form>
                    @else
                        <div class="text-muted"><i class="bi bi-check-circle text-success me-1"></i>এই ক্রেতার কোনো বকেয়া
                            নেই।</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>তারিখ</th>
                                <th>বিক্রি</th>
                                <th class="text-end">টাকা</th>
                                <th class="text-end">আদায়</th>
                                <th>অবস্থা</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sales as $s)
                                @php $meta = $s->typeMeta(); @endphp
                                <tr class="{{ $s->isVoid() ? 'is-void' : '' }}">
                                    <td class="text-nowrap">{{ $s->sold_at->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="ic sm {{ $meta['tone'] }} me-1"><i
                                                class="bi {{ $meta['icon'] }}"></i></span>
                                        {{ $meta['label'] }}
                                    </td>
                                    <td class="text-end text-nowrap amt">{{ \App\Support\Money::fmt($s->total_amount) }}
                                    </td>
                                    <td class="text-end text-nowrap">{{ \App\Support\Money::fmt($s->paid_amount) }}</td>
                                    <td>
                                        @if ($s->isVoid())
                                            <span class="pill gray">বাতিল</span>
                                        @else
                                            <span
                                                class="pill {{ $pillColors[$s->payment_status] ?? 'gray' }}">{{ $s->paymentLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('farms.sales.show', [$farm, $s]) }}" class="icon-btn sm"
                                            title="বিস্তারিত দেখুন" aria-label="বিস্তারিত দেখুন">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">এখনো কোনো বিক্রি নেই</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
