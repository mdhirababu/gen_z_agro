@extends('layouts.farm')
@section('title', 'প্রজনন')

@section('content')
    @php
        $statuses = config('farm.breeding_statuses');
        $totalAll = (int) $counts->sum();
        $q = fn(array $o = []) => array_filter(
            array_merge(['status' => $filters['status'], 'cow' => $filters['cow']], $o),
            fn($v) => $v !== null && $v !== '',
        );
        $dayText = fn(int $d) => $d < 0 ? abs($d) . ' দিন দেরি' : ($d === 0 ? 'আজ' : $d . ' দিন পরে');
    @endphp

    <div class="page-head">
        <div>
            <h1>প্রজনন</h1>
            <p>সেবা, গর্ভধারণ আর প্রসবের হিসাব।</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('farms.health.tasks', $farm) }}" class="btn btn-outline-primary">
                <i class="bi bi-bell me-1"></i>আসন্ন কাজ
            </a>
            <a href="{{ route('farms.breeding.create', $farm) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>নতুন সেবা
            </a>
        </div>
    </div>

    {{-- ===== সারসংক্ষেপ ===== --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="kpi">
                <div class="kpi-label">গর্ভবতী গরু</div>
                <div class="kpi-value">{{ $pregnantCount }}টি</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi peach">
                <div class="kpi-label">৩০ দিনের মধ্যে প্রসব</div>
                <div class="kpi-value">{{ $soonCount }}টি</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi sky">
                <div class="kpi-label">গর্ভ পরীক্ষার ফল বাকি</div>
                <div class="kpi-value">{{ $checkCount }}টি</div>
            </div>
        </div>
    </div>

    {{-- ===== অবস্থা ===== --}}
    <div class="tab-chips">
        <a href="{{ route('farms.breeding.index', [$farm] + $q(['status' => null])) }}"
            class="tab-chip {{ $filters['status'] === '' ? 'active' : '' }}">সব <span
                class="count">{{ $totalAll }}</span></a>
        @foreach ($statuses as $key => $meta)
            <a href="{{ route('farms.breeding.index', [$farm] + $q(['status' => $key])) }}"
                class="tab-chip {{ $filters['status'] === $key ? 'active' : '' }}">{{ $meta['label'] }} <span
                    class="count">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('farms.breeding.index', $farm) }}" class="row g-2 mb-3">
        @if ($filters['status'] !== '')
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
        @endif
        <div class="col-md-4">
            <select name="cow" class="form-select" onchange="this.form.submit()" aria-label="গরু">
                <option value="">সব গরু</option>
                @foreach ($cows as $c)
                    <option value="{{ $c->id }}" @selected($filters['cow'] === (string) $c->id)>{{ $c->tag_number }}@if ($c->name)
                            ({{ $c->name }})
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    @if ($events->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-hearts"></i>
                <h2>কোনো সেবা নথিভুক্ত নেই</h2>
                <p>প্রথম সেবা যোগ করলে গর্ভ পরীক্ষা ও প্রসবের তারিখ নিজে থেকে "আসন্ন কাজ"-এ আসবে।</p>
                <a href="{{ route('farms.breeding.create', $farm) }}" class="btn btn-primary">নতুন সেবা</a>
            </div>
        </div>
    @else
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>সেবার তারিখ</th>
                            <th>গরু</th>
                            <th>সেবা</th>
                            <th>অবস্থা</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $e)
                            @php
                                $meta = $e->statusMeta();
                                $today = now()->startOfDay();
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ $e->service_date->format('d/m/Y') }}</td>
                                <td>
                                    @if ($e->cow && !$e->cow->trashed())
                                        <a class="fw-semibold"
                                            href="{{ route('farms.cows.show', [$farm, $e->cow]) }}">{{ $e->cow->tag_number }}</a>
                                    @elseif ($e->cow)
                                        {{ $e->cow->tag_number }}
                                    @endif
                                </td>
                                <td>
                                    {{ $e->methodLabel() }}
                                    @if ($e->sire_name)
                                        <div class="small text-muted">{{ $e->sire_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="pill {{ $meta['pill'] }}">{{ $meta['label'] }}</span>
                                    @if ($e->status === 'pregnant' && $e->expected_calving_date)
                                        <div class="small text-muted mt-1">
                                            প্রসব: {{ $e->expected_calving_date->format('d/m/Y') }}
                                            ({{ $dayText((int) $today->diffInDays($e->expected_calving_date->copy()->startOfDay(), false)) }})
                                        </div>
                                    @elseif ($e->status === 'served' && $e->next_check_date)
                                        <div class="small text-muted mt-1">
                                            পরীক্ষা: {{ $e->next_check_date->format('d/m/Y') }}
                                            ({{ $dayText((int) $today->diffInDays($e->next_check_date->copy()->startOfDay(), false)) }})
                                        </div>
                                    @elseif ($e->outcome_date)
                                        <div class="small text-muted mt-1">{{ $e->outcome_date->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('farms.breeding.show', [$farm, $e]) }}" class="icon-btn sm"
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

        <div class="mt-3">{{ $events->links() }}</div>
    @endif
@endsection
