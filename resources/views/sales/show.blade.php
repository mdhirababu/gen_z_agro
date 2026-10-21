@extends('layouts.farm')
@section('title', 'বিক্রির বিস্তারিত')

@section('content')
    @php
        $meta = $sale->typeMeta();
        $due = $sale->dueAmount();
    @endphp

    <div x-data="{ vm: { open: false, url: '', title: '', sub: '' } }">
        <div class="page-head">
            <div class="d-flex align-items-center gap-3">
                <span class="ic {{ $meta['tone'] }}"><i class="bi {{ $meta['icon'] }}"></i></span>
                <div>
                    <h1>{{ $meta['label'] }}</h1>
                    <p>{{ $sale->sold_at->format('d/m/Y') }}@if ($sale->description)
                            , {{ $sale->description }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('farms.sales.index', $farm) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>তালিকা
                </a>
                @unless ($sale->isVoid())
                    <a href="{{ route('farms.sales.edit', [$farm, $sale]) }}" class="icon-btn" title="সম্পাদনা করুন"
                        aria-label="সম্পাদনা করুন">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="icon-btn danger" title="বিক্রিটি বাতিল করুন" aria-label="বিক্রিটি বাতিল করুন"
                        @click="vm = Object.assign(@js([
    'url' => route('farms.sales.void', [$farm, $sale]),
    'title' => 'বিক্রিটি বাতিল করবেন?',
    'sub' => $meta['label'] . ', ' . \App\Support\Money::fmt($sale->total_amount),
]), { open: true })">
                        <i class="bi bi-slash-circle"></i>
                    </button>
                @endunless
            </div>
        </div>

        @if ($sale->isVoid())
            <div class="alert alert-secondary">
                <strong>এই বিক্রি বাতিল করা হয়েছে।</strong>
                @if ($sale->void_reason)
                    কারণ: {{ $sale->void_reason }}.
                @endif
                @if ($sale->voider) বাতিল করেছেন {{ $sale->voider->name }}
                    @if ($sale->voided_at)
                        , {{ $sale->voided_at->format('d/m/Y') }}
                    @endif. @endif
                হিসাবের রিপোর্টে এটা ধরা হয় না।
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="kpi sky">
                    <div class="kpi-label">মোট বিক্রি</div>
                    <div class="kpi-value {{ $sale->isVoid() ? 'is-void' : '' }}">
                        {{ \App\Support\Money::fmt($sale->total_amount) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi">
                    <div class="kpi-label">আদায় হয়েছে</div>
                    <div class="kpi-value">{{ \App\Support\Money::fmt($sale->paid_amount) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi peach">
                    <div class="kpi-label">পাওনা</div>
                    <div class="kpi-value">{{ \App\Support\Money::fmt($due) }}</div>
                    <div class="kpi-note">
                        @if ($sale->isVoid())
                            <span class="pill gray">বাতিল</span>
                        @else
                            <span class="pill {{ $sale->paymentPill() }}">{{ $sale->paymentLabel() }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="panel h-auto">
                    <div class="panel-head">বিবরণ</div>
                    <div class="panel-body">
                        <dl class="dl-grid">
                            <div>
                                <dt>ধরন</dt>
                                <dd>{{ $meta['label'] }}</dd>
                            </div>
                            <div>
                                <dt>তারিখ</dt>
                                <dd>{{ $sale->sold_at->format('d/m/Y') }}</dd>
                            </div>
                            <div>
                                <dt>ক্রেতা</dt>
                                <dd>
                                    @if ($sale->customer && !$sale->customer->trashed())
                                        <a
                                            href="{{ route('farms.customers.show', [$farm, $sale->customer]) }}">{{ $sale->customer->name }}</a>
                                    @elseif ($sale->customer)
                                        {{ $sale->customer->name }}
                                    @else
                                        নগদ বিক্রি
                                    @endif
                                </dd>
                            </div>
                            @if ($sale->sale_type === 'cow')
                                <div>
                                    <dt>গরু</dt>
                                    <dd>
                                        @if ($sale->cow && !$sale->cow->trashed())
                                            <a
                                                href="{{ route('farms.cows.show', [$farm, $sale->cow]) }}">{{ $sale->cow->tag_number }}</a>
                                        @elseif ($sale->cow)
                                            {{ $sale->cow->tag_number }}
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            @if ((float) $sale->quantity > 0)
                                <div>
                                    <dt>পরিমাণ</dt>
                                    <dd>{{ rtrim(rtrim(number_format((float) $sale->quantity, 2), '0'), '.') }}
                                        {{ $sale->unit }}</dd>
                                </div>
                                <div>
                                    <dt>দর</dt>
                                    <dd>{{ \App\Support\Money::fmt($sale->unit_price) }} প্রতি {{ $sale->unit }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt>বিবরণ</dt>
                                <dd>{{ $sale->description ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt>নথিভুক্ত করেছেন</dt>
                                <dd>{{ $sale->creator ? $sale->creator->name : '—' }},
                                    {{ $sale->created_at->format('d/m/Y') }}</dd>
                            </div>
                            @if ($sale->notes)
                                <div style="grid-column: 1 / -1">
                                    <dt>নোট</dt>
                                    <dd>{{ $sale->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                @include('finance._payments', [
                    'record' => $sale,
                    'payments' => $payments,
                    'storeUrl' => route('farms.sales.payments.store', [$farm, $sale]),
                    'direction' => 'in',
                ])
            </div>
        </div>

        @include('finance._void_sheet')
    </div>
@endsection
