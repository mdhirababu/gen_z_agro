@extends('layouts.farm')
@section('title', 'খরচের বিস্তারিত')

@section('content')
    @php
        $meta = $expense->categoryMeta();
        $due = $expense->dueAmount();
    @endphp

    <div x-data="{ vm: { open: false, url: '', title: '', sub: '' } }">
        <div class="page-head">
            <div class="d-flex align-items-center gap-3">
                <span class="ic {{ $meta['tone'] }}"><i class="bi {{ $meta['icon'] }}"></i></span>
                <div>
                    <h1>{{ $meta['label'] }}</h1>
                    <p>{{ $expense->expense_date->format('d/m/Y') }}@if ($expense->description)
                            , {{ $expense->description }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('farms.expenses.index', $farm) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>তালিকা
                </a>
                @unless ($expense->isVoid())
                    <a href="{{ route('farms.expenses.edit', [$farm, $expense]) }}" class="icon-btn" title="সম্পাদনা করুন"
                        aria-label="সম্পাদনা করুন">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="icon-btn danger" title="খরচটি বাতিল করুন" aria-label="খরচটি বাতিল করুন"
                        @click="vm = Object.assign(@js([
    'url' => route('farms.expenses.void', [$farm, $expense]),
    'title' => 'খরচটি বাতিল করবেন?',
    'sub' => $meta['label'] . ', ' . \App\Support\Money::fmt($expense->total_amount),
]), { open: true })">
                        <i class="bi bi-slash-circle"></i>
                    </button>
                @endunless
            </div>
        </div>

        @if ($expense->isVoid())
            <div class="alert alert-secondary">
                <strong>এই খরচ বাতিল করা হয়েছে।</strong>
                @if ($expense->void_reason)
                    কারণ: {{ $expense->void_reason }}.
                @endif
                @if ($expense->voider) বাতিল করেছেন {{ $expense->voider->name }}
                    @if ($expense->voided_at)
                        , {{ $expense->voided_at->format('d/m/Y') }}
                    @endif. @endif
                হিসাবের রিপোর্টে এটা ধরা হয় না।
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="kpi peach">
                    <div class="kpi-label">মোট খরচ</div>
                    <div class="kpi-value {{ $expense->isVoid() ? 'is-void' : '' }}">
                        {{ \App\Support\Money::fmt($expense->total_amount) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi">
                    <div class="kpi-label">পরিশোধ হয়েছে</div>
                    <div class="kpi-value">{{ \App\Support\Money::fmt($expense->paid_amount) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi rose">
                    <div class="kpi-label">বকেয়া</div>
                    <div class="kpi-value">{{ \App\Support\Money::fmt($due) }}</div>
                    <div class="kpi-note">
                        @if ($expense->isVoid())
                            <span class="pill gray">বাতিল</span>
                        @else
                            <span class="pill {{ $expense->paymentPill() }}">{{ $expense->paymentLabel() }}</span>
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
                                <dd>{{ $expense->expense_date->format('d/m/Y') }}</dd>
                            </div>
                            <div>
                                <dt>কীসের খরচ</dt>
                                <dd>{{ $expense->description ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt>কার কাছ থেকে</dt>
                                <dd>{{ $expense->vendor_name ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt>পরিমাণ</dt>
                                <dd>{{ (float) $expense->quantity > 0 ? rtrim(rtrim(number_format((float) $expense->quantity, 2), '0'), '.') . ' ' . $expense->unit : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt>গরু</dt>
                                <dd>
                                    @if ($expense->cow && !$expense->cow->trashed())
                                        <a
                                            href="{{ route('farms.cows.show', [$farm, $expense->cow]) }}">{{ $expense->cow->tag_number }}</a>
                                    @elseif ($expense->cow)
                                        {{ $expense->cow->tag_number }}
                                    @else
                                        খামারের সাধারণ খরচ
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>নথিভুক্ত করেছেন</dt>
                                <dd>{{ $expense->creator ? $expense->creator->name : '—' }},
                                    {{ $expense->created_at->format('d/m/Y') }}</dd>
                            </div>
                            @if ($expense->notes)
                                <div style="grid-column: 1 / -1">
                                    <dt>নোট</dt>
                                    <dd>{{ $expense->notes }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                @include('finance._payments', [
                    'record' => $expense,
                    'payments' => $payments,
                    'storeUrl' => route('farms.expenses.payments.store', [$farm, $expense]),
                    'direction' => 'out',
                ])
            </div>
        </div>

        @include('finance._void_sheet')
    </div>
@endsection
