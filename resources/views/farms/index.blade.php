@extends('layouts.farm')
@section('title', 'আমার খামারসমূহ')

@section('content')
    @php
        $roleLabels = [
            'owner' => 'মালিক',
            'manager' => 'ম্যানেজার',
            'staff' => 'স্টাফ',
            'super_admin' => 'সুপার অ্যাডমিন',
        ];
        $tagColors = ['info' => 'blue', 'success' => 'green', 'warning' => 'orange', 'danger' => 'red'];
    @endphp

    <div class="page-head">
        <div>
            <h1>আমার খামারসমূহ</h1>
            <p>যে খামারে কাজ করতে চান সেটি বেছে নিন।</p>
        </div>
        <a href="{{ route('farms.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>নতুন খামার</a>
    </div>

    @if ($farms->isEmpty())
        <div class="panel">
            <div class="panel-body empty-state">
                <i class="bi bi-buildings"></i>
                <h2>এখনো কোনো খামার নেই</h2>
                <p>প্রথম খামার তৈরি করলে ১৪ দিনের ফ্রি ট্রায়াল শুরু হবে।</p>
                <a href="{{ route('farms.create') }}" class="btn btn-primary">খামার তৈরি করুন</a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($farms as $farm)
                @php
                    $sub = $farm->subscription;
                    $end = $sub ? $sub->endDate() : null;
                    $role = $farm->pivot->role ?? 'super_admin';
                @endphp
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('farms.dashboard', $farm) }}" class="farm-tile">
                        <span class="farm-avatar">{{ mb_substr($farm->name, 0, 1) }}</span>
                        <div class="min-w-0">
                            <div class="farm-title">{{ $farm->name }}</div>
                            <div class="text-muted small">{{ $farm->district ?: 'জেলা দেওয়া নেই' }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="tag gray">{{ $roleLabels[$role] ?? $role }}</span>
                                @if ($sub)
                                    <span class="tag {{ $tagColors[$sub->badgeClass()] ?? 'gray' }}">{{ $sub->plan->name }}:
                                        {{ $sub->statusLabel() }}</span>
                                @endif
                            </div>
                            @if ($end)
                                <div class="small text-muted mt-2">মেয়াদ: {{ $end->format('d/m/Y') }} পর্যন্ত</div>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
