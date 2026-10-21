@extends('layouts.farm')
@section('title', 'চিকিৎসা ও টিকা')

@section('content')
    @php
        $kinds = config('farm.health_kinds');
        $totalAll = (int) $counts->sum();
        $q = fn(array $o = []) => array_filter(
            array_merge(
                ['kind' => $filters['kind'], 'cow' => $filters['cow'], 'ongoing' => $filters['ongoing'] ? 1 : null],
                $o,
            ),
            fn($v) => $v !== null && $v !== '',
        );
    @endphp

    <div x-data="{ del: { open: false, url: '', title: '', sub: '' } }" @keydown.escape.window="del.open = false">
        <div class="page-head">
            <div>
                <h1>চিকিৎসা ও টিকা</h1>
                <p>গরুর চিকিৎসা, টিকা, কৃমিনাশক আর স্বাস্থ্য পরীক্ষার ইতিহাস।</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('farms.health.tasks', $farm) }}" class="btn btn-outline-primary">
                    <i class="bi bi-bell me-1"></i>আসন্ন কাজ
                    @if ($taskCount)
                        <span class="badge bg-warning ms-1">{{ $taskCount }}</span>
                    @endif
                </a>
                <a href="{{ route('farms.health.create', $farm) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>নতুন রেকর্ড
                </a>
            </div>
        </div>

        {{-- ===== ধরন ===== --}}
        <div class="tab-chips">
            <a href="{{ route('farms.health.index', [$farm] + $q(['kind' => null])) }}"
                class="tab-chip {{ $filters['kind'] === '' ? 'active' : '' }}">সব <span
                    class="count">{{ $totalAll }}</span></a>
            @foreach ($kinds as $key => $meta)
                <a href="{{ route('farms.health.index', [$farm] + $q(['kind' => $key])) }}"
                    class="tab-chip {{ $filters['kind'] === $key ? 'active' : '' }}">{{ $meta['label'] }} <span
                        class="count">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </div>

        {{-- ===== ফিল্টার ===== --}}
        <form method="GET" action="{{ route('farms.health.index', $farm) }}" class="row g-2 mb-3 align-items-center">
            @if ($filters['kind'] !== '')
                <input type="hidden" name="kind" value="{{ $filters['kind'] }}">
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
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="ongoing" id="ongoing" value="1"
                        @checked($filters['ongoing']) onchange="this.form.submit()">
                    <label class="form-check-label" for="ongoing">শুধু চলমান চিকিৎসা</label>
                </div>
            </div>
        </form>

        {{-- ===== তালিকা ===== --}}
        @if ($events->isEmpty())
            <div class="panel h-auto">
                <div class="panel-body empty-state">
                    <i class="bi bi-heart-pulse"></i>
                    <h2>কোনো রেকর্ড পাওয়া যায়নি</h2>
                    <p>প্রথম চিকিৎসা বা টিকার রেকর্ড যোগ করে শুরু করুন।</p>
                    <a href="{{ route('farms.health.create', $farm) }}" class="btn btn-primary">নতুন রেকর্ড</a>
                </div>
            </div>
        @else
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>তারিখ</th>
                                <th>গরু</th>
                                <th>রেকর্ড</th>
                                <th>পরের তারিখ</th>
                                @if ($canFinance)
                                    <th class="text-end">খরচ</th>
                                @endif
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $e)
                                @php $meta = $e->kindMeta(); @endphp
                                <tr>
                                    <td class="text-nowrap">{{ $e->event_date->format('d/m/Y') }}</td>
                                    <td>
                                        @if ($e->cow && !$e->cow->trashed())
                                            <a class="fw-semibold"
                                                href="{{ route('farms.cows.show', [$farm, $e->cow]) }}">{{ $e->cow->tag_number }}</a>
                                        @elseif ($e->cow)
                                            {{ $e->cow->tag_number }}
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-start gap-2">
                                            <span class="ic sm {{ $meta['tone'] }}"><i
                                                    class="bi {{ $meta['icon'] }}"></i></span>
                                            <div class="min-w-0">
                                                <div class="fw-semibold">{{ $e->title }}</div>
                                                <div class="small text-muted">
                                                    {{ $meta['label'] }}@if ($e->medicine)
                                                        , {{ $e->medicine }}
                                                        @endif@if ($e->vet_name)
                                                            , ডা. {{ $e->vet_name }}
                                                        @endif
                                                </div>
                                                <div class="d-flex flex-wrap gap-1 mt-1">
                                                    @if ($e->kind === 'treatment')
                                                        <span
                                                            class="pill {{ $e->outcome === 'recovered' ? 'green' : 'orange' }}">{{ $e->outcome === 'recovered' ? 'সুস্থ' : 'চলছে' }}</span>
                                                    @endif
                                                    @if ($e->withdrawalActive())
                                                        <span class="pill orange">দুধ বিক্রি বন্ধ:
                                                            {{ $e->milk_withdrawal_until->format('d/m') }} পর্যন্ত</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($e->follow_up_status === 'open' && $e->next_due_date)
                                            @php $d = $e->daysToDue(); @endphp
                                            {{ $e->next_due_date->format('d/m/Y') }}
                                            <div>
                                                <span class="pill {{ $d < 0 ? 'red' : ($d <= 7 ? 'orange' : 'green') }}">
                                                    {{ $d < 0 ? abs($d) . ' দিন দেরি' : ($d === 0 ? 'আজ' : $d . ' দিন পরে') }}
                                                </span>
                                            </div>
                                        @elseif ($e->next_due_date)
                                            <span class="small text-muted">সম্পন্ন</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    @if ($canFinance)
                                        <td class="text-end text-nowrap">
                                            @if ($e->expense)
                                                <a
                                                    href="{{ route('farms.expenses.show', [$farm, $e->expense]) }}">{{ \App\Support\Money::fmt($e->expense->total_amount) }}</a>
                                                @if ($e->expense->isVoid())
                                                    <span class="pill gray">বাতিল</span>
                                                @endif
                                                @if (!$e->expense->cow_id)
                                                    <div class="small text-muted">সব গরুর মোট</div>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif
                                    <td class="text-end text-nowrap">
                                        @if ($e->kind === 'treatment' && $e->outcome === 'ongoing')
                                            <form method="POST" action="{{ route('farms.health.recover', [$farm, $e]) }}"
                                                class="d-inline">
                                                @csrf
                                                <button class="icon-btn sm" title="সুস্থ হয়েছে"
                                                    aria-label="সুস্থ হয়েছে"><i class="bi bi-check2-circle"></i></button>
                                            </form>
                                        @endif
                                        @if ($canManage)
                                            <a href="{{ route('farms.health.edit', [$farm, $e]) }}" class="icon-btn sm"
                                                title="সম্পাদনা করুন" aria-label="সম্পাদনা করুন"><i
                                                    class="bi bi-pencil"></i></a>
                                            <button type="button" class="icon-btn sm danger" title="রেকর্ড সরান"
                                                aria-label="রেকর্ড সরান"
                                                @click="del = Object.assign(@js([
    'url' => route('farms.health.destroy', [$farm, $e]),
    'title' => 'রেকর্ডটি সরাবেন?',
    'sub' => $e->title . ', ' . $e->event_date->format('d/m/Y'),
]), { open: true })">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3">{{ $events->links() }}</div>
        @endif

        {{-- ===== সরানোর পপ-আপ ===== --}}
        <div class="sheet-backdrop" x-show="del.open" x-transition.opacity style="display: none"
            @click.self="del.open = false">
            <div class="sheet" role="dialog" aria-modal="true">
                <form method="POST" :action="del.url">
                    @csrf
                    @method('DELETE')
                    <div class="sheet-head">
                        <span class="ic rose"><i class="bi bi-trash3"></i></span>
                        <div>
                            <h3 x-text="del.title"></h3>
                            <p x-text="del.sub"></p>
                        </div>
                    </div>
                    <p class="sheet-note mb-0">রেকর্ডটি তালিকা থেকে সরে যাবে। এর সঙ্গে জুড়ে থাকা খরচ (থাকলে) বদলাবে না,
                        সেটা খরচের পাতা থেকে আলাদা করে বাতিল করতে হয়।</p>
                    <div class="sheet-actions">
                        <button type="button" class="btn btn-outline-secondary" @click="del.open = false">ফিরে
                            যান</button>
                        <button type="submit" class="btn btn-danger">হ্যাঁ, সরান</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
