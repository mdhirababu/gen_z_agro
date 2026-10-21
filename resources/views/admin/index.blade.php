@extends('layouts.farm')
@section('title', 'অ্যাডমিন')

@section('content')
    <div class="page-head">
        <div>
            <h1>অ্যাডমিন ড্যাশবোর্ড</h1>
            <p>সব খামার ও তাদের সাবস্ক্রিপশনের অবস্থা।</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.payments.index') }}" class="btn btn-primary">
                <i class="bi bi-cash-coin me-1"></i>পেমেন্ট যাচাই
                @if ($pendingCount)
                    <span class="badge bg-warning ms-1">{{ $pendingCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-layers me-1"></i>প্ল্যান
            </a>
        </div>
    </div>

    {{-- ===== সারসংক্ষেপ ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="kpi">
                <div class="kpi-label">মোট খামার</div>
                <div class="kpi-value">{{ $stats['farms'] }}টি</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi sky">
                <div class="kpi-label">সক্রিয় সাবস্ক্রিপশন</div>
                <div class="kpi-value">{{ $stats['active'] }}টি</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi lilac">
                <div class="kpi-label">ট্রায়ালে আছে</div>
                <div class="kpi-value">{{ $stats['trial'] }}টি</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="kpi peach">
                <div class="kpi-label">মেয়াদ শেষ বা বকেয়া</div>
                <div class="kpi-value">{{ $stats['attention'] }}টি</div>
            </div>
        </div>
    </div>

    {{-- ===== খোঁজা ===== --}}
    <form method="GET" action="{{ route('admin.index') }}" class="d-flex gap-2 mb-3" style="max-width: 36rem">
        <div class="field-control flex-grow-1">
            <i class="bi bi-search lead-ic"></i>
            <input type="text" name="q" value="{{ $q }}" class="form-control"
                placeholder="খামার বা মালিকের নাম, ইমেইল দিয়ে খুঁজুন">
        </div>
        <button class="btn btn-primary">খুঁজুন</button>
        @if ($q !== '')
            <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary" title="খোঁজা মুছুন"
                aria-label="খোঁজা মুছুন">
                <i class="bi bi-x-lg"></i>
            </a>
        @endif
    </form>

    {{-- ===== খামারের তালিকা ===== --}}
    @if ($farms->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-buildings"></i>
                <h2>{{ $q !== '' ? 'কোনো খামার পাওয়া যায়নি' : 'এখনো কোনো খামার নেই' }}</h2>
                <p>{{ $q !== '' ? 'অন্য নাম বা ইমেইল দিয়ে আবার খুঁজে দেখুন।' : 'কেউ খামার তৈরি করলে এখানে দেখা যাবে।' }}
                </p>
            </div>
        </div>
    @else
        @php $pillColors = ['info' => 'blue', 'success' => 'green', 'warning' => 'orange', 'danger' => 'red']; @endphp

        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>খামার</th>
                            <th>মালিক</th>
                            <th>প্ল্যান</th>
                            <th>অবস্থা</th>
                            <th>মেয়াদ শেষ</th>
                            <th>তৈরি হয়েছে</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($farms as $farm)
                            @php
                                $sub = $farm->subscription;
                                $end = $sub ? $sub->endDate() : null;
                                $days = $sub ? $sub->daysLeft() : null;
                                $live = $sub && in_array($sub->currentStatus(), ['trial', 'active'], true);
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="farm-avatar"
                                            style="width: 2.4rem; height: 2.4rem; font-size: 1rem; border-radius: 0.8rem">{{ mb_substr($farm->name, 0, 1) }}</span>
                                        <div>
                                            <div class="fw-semibold">{{ $farm->name }}</div>
                                            <div class="small text-muted">{{ $farm->district ?: '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    {{ $farm->owner->name }}
                                    <div class="small text-muted">{{ $farm->owner->email }}</div>
                                </td>
                                <td>{{ $sub ? $sub->plan->name : '—' }}</td>
                                <td>
                                    @if ($sub)
                                        <span
                                            class="pill {{ $pillColors[$sub->badgeClass()] ?? 'gray' }}">{{ $sub->statusLabel() }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    {{ $end ? $end->format('d/m/Y') : '—' }}
                                    @if ($live && $days !== null)
                                        <div class="small text-muted">{{ $days }} দিন বাকি</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ $farm->created_at->format('d/m/Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('farms.dashboard', $farm) }}" class="icon-btn sm"
                                        title="খামারটি খুলুন" aria-label="খামারটি খুলুন">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $farms->links() }}</div>
    @endif
@endsection
