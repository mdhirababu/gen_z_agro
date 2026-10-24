@extends('layouts.farm')
@section('title', $cow->tag_number)

@section('content')
    @php
        $cat = config('farm.categories')[$cow->category] ?? ['label' => $cow->category, 'tag' => 'gray'];
        $breed = $cow->breed ? config('farm.breeds')[$cow->breed] ?? $cow->breed : null;
        $sexLabel = config('farm.sexes')[$cow->sex] ?? $cow->sex;
        $acqLabel = config('farm.acquisitions')[$cow->acquisition_type] ?? $cow->acquisition_type;
        $statusLabel = config('farm.statuses')[$cow->status] ?? $cow->status;
        $photo = $cow->photoUrl();

        $user = auth()->user();
        $canEdit = $user->can('update', $cow);
        $canDelete = $user->can('delete', $cow);
        $canAddWeight = $user->can('addWeight', $cow);
        $canDeleteWeight = $user->can('deleteWeight', $cow);
        $canCreate = $user->can('create', [App\Models\Cow::class, $farm]);

        // ওজনের লাইন চার্ট (শেষ ২০টি)
        $vals = $weights
            ->pluck('weight_kg')
            ->map(fn($v) => (float) $v)
            ->take(-20)
            ->values()
            ->all();
        $recent = $weights->take(-20)->values();
        $n = count($vals);
        $w = 560;
        $h = 190;
        $padL = 40;
        $padB = 24;
        $padT = 12;
        $padR = 10;
        $pts = [];
        $ticks = [];
        if ($n >= 2) {
            $min = min($vals);
            $max = max($vals);
            $span = max($max - $min, 1);
            $lo = $min - $span * 0.25;
            $hi = $max + $span * 0.25;
            foreach ($vals as $i => $v) {
                $x = $padL + $i * (($w - $padL - $padR) / ($n - 1));
                $y = $padT + ($h - $padT - $padB) * (1 - ($v - $lo) / ($hi - $lo));
                $pts[] = [round($x, 1), round($y, 1)];
            }
            $ticks = [$lo, ($lo + $hi) / 2, $hi];
        }
    @endphp

    <div x-data="{ tab: 'weight', open: false, del: {} }" @keydown.escape.window="open = false">
        <div class="page-head">
            <div>
                <h1>{{ $cow->tag_number }}@if ($cow->name)
                        <span class="fw-normal text-muted fs-5">{{ $cow->name }}</span>
                    @endif
                </h1>
                <p>{{ $breed ?: 'জাত দেওয়া নেই' }}</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('farms.cows.index', $farm) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>তালিকা
                </a>
                @if ($finance && $cow->status === 'active')
                    <a href="{{ route('farms.sales.create', [$farm, 'type' => 'cow', 'cow' => $cow->id]) }}"
                        class="btn btn-outline-primary">
                        <i class="bi bi-cash-coin me-1"></i>বিক্রি করুন
                    </a>
                @endif
                @if ($canEdit)
                    <a href="{{ route('farms.cows.edit', [$farm, $cow]) }}" class="btn btn-primary">
                        <i class="bi bi-pencil me-1"></i>সম্পাদনা
                    </a>
                @endif
                @if ($canDelete)
                    <button type="button" class="icon-btn danger" title="গরুটি সরান" aria-label="গরুটি সরান"
                        @click="del = @js([
    'url' => route('farms.cows.destroy', [$farm, $cow]),
    'title' => 'গরুটি সরাবেন?',
    'sub' => $cow->tag_number,
    'note' => 'গরুটি তালিকা থেকে সরে যাবে। ট্যাগ নম্বরটি এই খামারে আর নতুন করে ব্যবহার করা যাবে না। শুধু ভুল করে যোগ করলেই এটা করুন, মারা গেলে বা সরিয়ে দিলে সম্পাদনা থেকে অবস্থা বদলান।',
]); open = true">
                        <i class="bi bi-trash3"></i>
                    </button>
                @endif
            </div>
        </div>

        {{-- ===== পরিচিতি ===== --}}
        <div class="panel cow-hero h-auto mb-3">
            <div class="cow-hero-photo">
                @if ($photo)
                    <img src="{{ $photo }}" alt="{{ $cow->tag_number }}">
                @else
                    <i class="bi bi-image"></i>
                @endif
            </div>

            <div>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <span class="tag {{ $cat['tag'] }}">{{ $cat['label'] }}</span>
                    <span class="tag blue">{{ $sexLabel }}</span>
                    <span
                        class="tag {{ $cow->status === 'active' ? 'green' : ($cow->status === 'dead' ? 'red' : 'gray') }}">{{ $statusLabel }}</span>
                </div>

                <div class="row g-2">
                    <div class="col-6 col-xl-3">
                        <div class="tile mint">
                            <div class="tile-label">বয়স</div>
                            <div class="tile-value">{{ $cow->ageLabel() ?? 'অজানা' }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="tile sky">
                            <div class="tile-label">সর্বশেষ ওজন</div>
                            <div class="tile-value">{{ $stats ? $stats['latest'] . ' কেজি' : '—' }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="tile sand">
                            <div class="tile-label">{{ $cow->baseValueLabel() }}</div>
                            <div class="tile-value">
                                {{ $cow->acquisition_type === 'born' ? '—' : '৳ ' . number_format($cow->baseValue(), 0) }}
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        @if ($cow->sex === 'female')
                            <div class="tile lilac">
                                <div class="tile-label">বাছুর</div>
                                <div class="tile-value">{{ $calves->count() }}টি</div>
                            </div>
                        @else
                            <div class="tile lilac">
                                <div class="tile-label">দৈনিক ওজন বৃদ্ধি</div>
                                <div class="tile-value">
                                    {{ $stats && $stats['count'] > 1 ? $stats['daily'] . ' কেজি' : '—' }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== ট্যাব ===== --}}
        <div class="tabs-x" role="tablist">
            <button type="button" :class="{ active: tab === 'weight' }" @click="tab = 'weight'">ওজন</button>
            <button type="button" :class="{ active: tab === 'family' }" @click="tab = 'family'">পরিবার</button>
            <button type="button" :class="{ active: tab === 'details' }" @click="tab = 'details'">বিবরণ</button>
            @if ($finance)
                <button type="button" :class="{ active: tab === 'finance' }" @click="tab = 'finance'">হিসাব</button>
            @endif
            <button type="button" :class="{ active: tab === 'health' }" @click="tab = 'health'">স্বাস্থ্য</button>
            @if ($cow->sex === 'female')
                <button type="button" :class="{ active: tab === 'breeding' }" @click="tab = 'breeding'">প্রজনন</button>
            @endif
            <button type="button" disabled title="শীঘ্রই আসছে">দুধ</button>
        </div>

        {{-- ===== ওজন ===== --}}
        <div x-show="tab === 'weight'">
            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="panel h-auto mb-3">
                        <div class="panel-head">ওজনের ধারা (কেজি)</div>
                        <div class="panel-body">
                            @if ($n >= 2)
                                <svg class="chart" viewBox="0 0 {{ $w }} {{ $h }}" role="img"
                                    aria-label="ওজনের লাইন চার্ট">
                                    @foreach ($ticks as $t)
                                        @php $ty = $padT + ($h - $padT - $padB) * (1 - ($t - $lo) / ($hi - $lo)); @endphp
                                        <line class="grid" x1="{{ $padL }}" x2="{{ $w - $padR }}"
                                            y1="{{ $ty }}" y2="{{ $ty }}" />
                                        <text class="axis" x="{{ $padL - 6 }}" y="{{ $ty + 4 }}"
                                            text-anchor="end">{{ round($t) }}</text>
                                    @endforeach
                                    <polyline class="line line-rev"
                                        points="{{ implode(' ', array_map(fn($p) => $p[0] . ',' . $p[1], $pts)) }}" />
                                    @foreach ($pts as $p)
                                        <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="3.5"
                                            fill="#1f8a4f" />
                                    @endforeach
                                    <text class="axis" x="{{ $padL }}" y="{{ $h - 6 }}"
                                        text-anchor="start">{{ $recent->first()->measured_at->format('d/m/y') }}</text>
                                    <text class="axis" x="{{ $w - $padR }}" y="{{ $h - 6 }}"
                                        text-anchor="end">{{ $recent->last()->measured_at->format('d/m/y') }}</text>
                                </svg>

                                @if ($stats)
                                    <div class="d-flex flex-wrap gap-3 small text-muted mt-2">
                                        <span>মোট বৃদ্ধি: <strong
                                                class="text-body">{{ $stats['gain'] > 0 ? '+' : '' }}{{ $stats['gain'] }}
                                                কেজি</strong></span>
                                        <span>দৈনিক গড়: <strong class="text-body">{{ $stats['daily'] }}
                                                কেজি</strong></span>
                                        <span>{{ $stats['count'] }}টি রেকর্ড</span>
                                    </div>
                                @endif
                            @else
                                <div class="empty-state py-4">
                                    <i class="bi bi-graph-up"></i>
                                    <p class="mb-0 mt-2">চার্ট দেখতে অন্তত দুটি ওজন যোগ করুন।</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>তারিখ</th>
                                        <th class="text-end">ওজন (কেজি)</th>
                                        <th class="text-end">আগের থেকে</th>
                                        <th>নোট</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rows as $row)
                                        <tr>
                                            <td class="text-nowrap">{{ $row['record']->measured_at->format('d/m/Y') }}
                                            </td>
                                            <td class="text-end fw-semibold">{{ $row['record']->weight_kg }}</td>
                                            <td class="text-end">
                                                @if ($row['diff'] === null)
                                                    <span class="text-muted">—</span>
                                                @else
                                                    <span
                                                        class="pill {{ $row['diff'] >= 0 ? 'green' : 'orange' }}">{{ $row['diff'] > 0 ? '+' : '' }}{{ $row['diff'] }}</span>
                                                @endif
                                            </td>
                                            <td class="small text-muted">{{ $row['record']->note ?: '—' }}</td>
                                            <td class="text-end">
                                                @if ($canDeleteWeight)
                                                    <button type="button" class="icon-btn sm danger"
                                                        title="রেকর্ডটি মুছুন" aria-label="রেকর্ডটি মুছুন"
                                                        @click="del = @js([
    'url' => route('farms.cows.weights.destroy', [$farm, $cow, $row['record']]),
    'title' => 'ওজনের রেকর্ড মুছবেন?',
    'sub' => $row['record']->measured_at->format('d/m/Y') . ': ' . $row['record']->weight_kg . ' কেজি',
    'note' => 'ভুল ওজন যোগ হয়ে থাকলে মুছে আবার সঠিকটা যোগ করুন।',
]); open = true">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">এখনো কোনো ওজন যোগ করা
                                                হয়নি</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    @if ($canAddWeight)
                        <div class="panel h-auto">
                            <div class="panel-head">নতুন ওজন যোগ করুন</div>
                            <div class="panel-body">
                                <form method="POST" action="{{ route('farms.cows.weights.store', [$farm, $cow]) }}">
                                    @csrf
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="field">
                                                <label for="weight_kg" class="form-label">ওজন (কেজি)</label>
                                                <input id="weight_kg" type="number" step="0.1" min="1"
                                                    name="weight_kg" value="{{ old('weight_kg') }}"
                                                    class="form-control @error('weight_kg') is-invalid @enderror" required>
                                                @error('weight_kg')
                                                    <div class="field-error">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="field">
                                                <label for="measured_at" class="form-label">তারিখ</label>
                                                <input id="measured_at" type="date" name="measured_at"
                                                    value="{{ old('measured_at', now()->toDateString()) }}"
                                                    max="{{ now()->toDateString() }}"
                                                    class="form-control @error('measured_at') is-invalid @enderror"
                                                    required>
                                                @error('measured_at')
                                                    <div class="field-error">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label for="note" class="form-label">নোট (ঐচ্ছিক)</label>
                                        <input id="note" type="text" name="note" value="{{ old('note') }}"
                                            class="form-control">
                                    </div>
                                    <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>ওজন যোগ
                                        করুন</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== পরিবার ===== --}}
        <div x-show="tab === 'family'" x-cloak>
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="panel h-auto">
                        <div class="panel-head">মা গরু</div>
                        <div class="panel-body">
                            @if ($cow->mother)
                                <div class="d-flex align-items-center gap-3">
                                    <span class="ic mint"><i class="bi bi-person-heart"></i></span>
                                    <div>
                                        @if ($cow->mother->trashed())
                                            <div class="fw-semibold">{{ $cow->mother->tag_number }}</div>
                                            <div class="small text-muted">তালিকা থেকে সরানো হয়েছে</div>
                                        @else
                                            <a class="fw-semibold"
                                                href="{{ route('farms.cows.show', [$farm, $cow->mother]) }}">{{ $cow->mother->tag_number }}</a>
                                            @if ($cow->mother->name)
                                                <div class="small text-muted">{{ $cow->mother->name }}</div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="text-muted">মায়ের তথ্য দেওয়া নেই।</div>
                            @endif

                            @if ($cow->sire_name)
                                <div class="mt-3 pt-3 border-top small"><span class="text-muted">বাবা:</span>
                                    <strong>{{ $cow->sire_name }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="panel h-auto">
                        <div class="panel-head d-flex justify-content-between align-items-center">
                            <span>বাছুর ({{ $calves->count() }}টি)</span>
                            @if ($cow->sex === 'female' && $canCreate)
                                <a href="{{ route('farms.cows.create', [$farm, 'mother' => $cow->id]) }}"
                                    class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i>বাছুর জন্ম
                                </a>
                            @endif
                        </div>
                        <div class="panel-body">
                            @forelse ($calves as $calf)
                                @php $cm = config('farm.categories')[$calf->category] ?? ['label' => $calf->category, 'tag' => 'gray']; @endphp
                                <div
                                    class="d-flex align-items-center justify-content-between py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <div>
                                        <a class="fw-semibold"
                                            href="{{ route('farms.cows.show', [$farm, $calf]) }}">{{ $calf->tag_number }}</a>
                                        <div class="small text-muted">
                                            জন্ম: {{ $calf->birth_date ? $calf->birth_date->format('d/m/Y') : 'অজানা' }}
                                            @if ($calf->ageLabel())
                                                ({{ $calf->ageLabel() }})
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1">
                                        <span class="tag {{ $cm['tag'] }}">{{ $cm['label'] }}</span>
                                        <span class="tag blue">{{ config('farm.sexes')[$calf->sex] ?? $calf->sex }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">এখনো কোনো বাছুরের তথ্য নেই।</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== বিবরণ ===== --}}
        <div x-show="tab === 'details'" x-cloak>
            <div class="panel h-auto">
                <div class="panel-body">
                    <dl class="dl-grid">
                        <div>
                            <dt>ট্যাগ নম্বর</dt>
                            <dd>{{ $cow->tag_number }}</dd>
                        </div>
                        <div>
                            <dt>নাম</dt>
                            <dd>{{ $cow->name ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt>জাত</dt>
                            <dd>{{ $breed ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt>লিঙ্গ</dt>
                            <dd>{{ $sexLabel }}</dd>
                        </div>
                        <div>
                            <dt>জন্মতারিখ</dt>
                            <dd>{{ $cow->birth_date ? $cow->birth_date->format('d/m/Y') : '—' }}@if ($cow->birth_date_estimated)
                                    (আনুমানিক)
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>উৎস</dt>
                            <dd>{{ $acqLabel }}</dd>
                        </div>
                        @if ($cow->acquisition_type === 'purchased')
                            <div>
                                <dt>ক্রয়ের তারিখ</dt>
                                <dd>{{ $cow->purchase_date ? $cow->purchase_date->format('d/m/Y') : '—' }}</dd>
                            </div>
                            <div>
                                <dt>ক্রয়মূল্য</dt>
                                <dd>৳ {{ number_format($cow->baseValue(), 0) }}</dd>
                            </div>
                        @elseif ($cow->acquisition_type === 'existing')
                            <div>
                                <dt>প্রারম্ভিক মূল্য</dt>
                                <dd>৳ {{ number_format($cow->baseValue(), 0) }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt>অবস্থা</dt>
                            <dd>{{ $statusLabel }}@if ($cow->exit_date)
                                    ({{ $cow->exit_date->format('d/m/Y') }})
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>যোগ করেছেন</dt>
                            <dd>{{ $cow->creator ? $cow->creator->name : '—' }}, {{ $cow->created_at->format('d/m/Y') }}
                            </dd>
                        </div>
                        @if ($cow->notes)
                            <div style="grid-column: 1 / -1">
                                <dt>নোট</dt>
                                <dd>{{ $cow->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        {{-- ===== স্বাস্থ্য ===== --}}
        <div x-show="tab === 'health'" x-cloak>
            @if ($withdrawalUntil)
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    ওষুধের কারণে <strong>{{ $withdrawalUntil->format('d/m/Y') }}</strong> পর্যন্ত এই গরুর দুধ বিক্রি করা
                    যাবে না।
                </div>
            @endif

            <div class="panel h-auto">
                <div class="panel-head d-flex justify-content-between align-items-center">
                    <span>স্বাস্থ্যের ইতিহাস</span>
                    <a href="{{ route('farms.health.create', [$farm, 'cow' => $cow->id]) }}"
                        class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>রেকর্ড যোগ
                    </a>
                </div>
                <div class="panel-body">
                    @forelse ($healthEvents as $he)
                        @php $hm = $he->kindMeta(); @endphp
                        <div class="d-flex gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <span class="ic sm {{ $hm['tone'] }}"><i class="bi {{ $hm['icon'] }}"></i></span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">
                                    {{ $he->title }}
                                    <span class="pill {{ $hm['pill'] }} ms-1">{{ $hm['label'] }}</span>
                                    @if ($he->kind === 'treatment')
                                        <span
                                            class="pill {{ $he->outcome === 'recovered' ? 'green' : 'orange' }}">{{ $he->outcome === 'recovered' ? 'সুস্থ' : 'চলছে' }}</span>
                                    @endif
                                </div>
                                <div class="small text-muted">
                                    {{ $he->event_date->format('d/m/Y') }}@if ($he->medicine)
                                        , {{ $he->medicine }}
                                        @endif@if ($he->vet_name)
                                            , ডা. {{ $he->vet_name }}
                                        @endif
                                </div>
                                @if ($he->details)
                                    <div class="small">{{ $he->details }}</div>
                                @endif
                                @if ($he->follow_up_status === 'open' && $he->next_due_date)
                                    <div class="small mt-1">পরের তারিখ:
                                        <strong>{{ $he->next_due_date->format('d/m/Y') }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @empty
                            <div class="text-muted">এই গরুর কোনো স্বাস্থ্য রেকর্ড নেই।</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ===== হিসাব ===== --}}
            @if ($finance)
                <div x-show="tab === 'finance'" x-cloak>
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="panel h-auto">
                                <div class="panel-head d-flex justify-content-between align-items-center">
                                    <span>মোট বিনিয়োগ</span>
                                    <a href="{{ route('farms.expenses.create', [$farm, 'cow' => $cow->id]) }}"
                                        class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-lg me-1"></i>খরচ যোগ
                                    </a>
                                </div>
                                <div class="panel-body">
                                    <div class="d-flex justify-content-between py-2 border-bottom">
                                        <span>{{ $cow->baseValueLabel() }}</span>
                                        <strong>{{ $cow->acquisition_type === 'born' ? 'খামারে জন্ম, ০' : \App\Support\Money::fmt($cow->baseValue()) }}</strong>
                                    </div>

                                    @forelse ($finance['costs'] as $cost)
                                        @php
                                            $cm = config('farm.expense_categories')[$cost->category] ?? [
                                                'label' => $cost->category,
                                                'icon' => 'bi-receipt',
                                                'tone' => 'sand',
                                            ];
                                            $share =
                                                $finance['expenseTotal'] > 0
                                                    ? round(($cost->total / $finance['expenseTotal']) * 100)
                                                    : 0;
                                        @endphp
                                        <div class="py-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><span class="ic sm {{ $cm['tone'] }} me-2"><i
                                                            class="bi {{ $cm['icon'] }}"></i></span>{{ $cm['label'] }}</span>
                                                <strong>{{ \App\Support\Money::fmt($cost->total) }}</strong>
                                            </div>
                                            <div class="usage-bar mt-2"><span style="width: {{ $share }}%"></span>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-muted py-2 border-bottom">এই গরুর নামে এখনো কোনো খরচ নেই।</div>
                                    @endforelse

                                    <div class="d-flex justify-content-between pt-3">
                                        <span class="fw-semibold">মোট বিনিয়োগ</span>
                                        <span
                                            class="fw-semibold fs-5">{{ \App\Support\Money::fmt($finance['invested']) }}</span>
                                    </div>
                                    <div class="small text-muted mt-2">
                                        যৌথ খরচ (শ্রমিক, বিদ্যুৎ ইত্যাদি) এখানে ধরা নেই। রিপোর্টে সেগুলো ভাগ করে দেখানো হবে।
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="panel h-auto">
                                <div class="panel-head">বিক্রির ফলাফল</div>
                                <div class="panel-body">
                                    @if ($finance['sale'])
                                        <div class="d-flex justify-content-between py-2 border-bottom">
                                            <span>বিক্রির টাকা</span>
                                            <strong>{{ \App\Support\Money::fmt($finance['sale']->total_amount) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom">
                                            <span>মোট বিনিয়োগ</span>
                                            <strong>{{ \App\Support\Money::fmt($finance['invested']) }}</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center pt-3">
                                            <span class="fw-semibold">আনুমানিক
                                                {{ $finance['result'] >= 0 ? 'লাভ' : 'ক্ষতি' }}</span>
                                            <span
                                                class="pill {{ $finance['result'] >= 0 ? 'green' : 'red' }} fs-6">{{ \App\Support\Money::fmt(abs($finance['result'])) }}</span>
                                        </div>
                                        <a href="{{ route('farms.sales.show', [$farm, $finance['sale']]) }}"
                                            class="btn btn-sm btn-outline-secondary mt-3">
                                            <i class="bi bi-eye me-1"></i>বিক্রির বিবরণ
                                        </a>
                                    @elseif ($cow->status === 'active')
                                        <div class="text-muted mb-3">এই গরুটি এখনো বিক্রি হয়নি।</div>
                                        <a href="{{ route('farms.sales.create', [$farm, 'type' => 'cow', 'cow' => $cow->id]) }}"
                                            class="btn btn-primary">
                                            <i class="bi bi-cash-coin me-1"></i>বিক্রি করুন
                                        </a>
                                    @else
                                        <div class="text-muted">এই গরুর কোনো বিক্রি নথিভুক্ত নেই।</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ===== মোছার নিশ্চিতকরণ ===== --}}
            <div class="sheet-backdrop" x-show="open" x-transition.opacity style="display: none"
                @click.self="open = false">
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

                        <p class="sheet-note mb-0" x-text="del.note"></p>

                        <div class="sheet-actions">
                            <button type="button" class="btn btn-outline-secondary" @click="open = false">ফিরে যান</button>
                            <button type="submit" class="btn btn-danger">হ্যাঁ, মুছুন</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ===== প্রজনন ===== --}}
        @if ($cow->sex === 'female')
            <div x-show="tab === 'breeding'" x-cloak>
                @if ($activePregnancy)
                    @php
                        $toCalf = $activePregnancy->expected_calving_date
                            ? (int) now()
                                ->startOfDay()
                                ->diffInDays($activePregnancy->expected_calving_date->copy()->startOfDay(), false)
                            : null;
                    @endphp
                    <div class="alert alert-success">
                        <i class="bi bi-hearts me-1"></i>
                        গাভিটি <strong>গর্ভবতী</strong>
                        @if ($activePregnancy->expected_calving_date), প্রসবের সম্ভাব্য তারিখ
                            {{ $activePregnancy->expected_calving_date->format('d/m/Y') }}
                            @if ($toCalf !== null)
                                ({{ $toCalf < 0 ? abs($toCalf) . ' দিন দেরি' : ($toCalf === 0 ? 'আজ' : $toCalf . ' দিন পরে') }})
                            @endif
                        @endif।
                        <a href="{{ route('farms.breeding.show', [$farm, $activePregnancy]) }}">বিস্তারিত</a>
                    </div>
                @endif

                <div class="panel h-auto">
                    <div class="panel-head d-flex justify-content-between align-items-center">
                        <span>প্রজননের ইতিহাস</span>
                        @if ($cow->status === 'active' && $cow->category !== 'calf' && !$activePregnancy)
                            <a href="{{ route('farms.breeding.create', [$farm, 'cow' => $cow->id]) }}"
                                class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-lg me-1"></i>সেবা যোগ
                            </a>
                        @endif
                    </div>
                    <div class="panel-body">
                        @forelse ($breedingEvents as $be)
                            @php $bm = $be->statusMeta(); @endphp
                            <div
                                class="d-flex justify-content-between align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div>
                                    <a class="fw-semibold"
                                        href="{{ route('farms.breeding.show', [$farm, $be]) }}">{{ $be->service_date->format('d/m/Y') }}</a>
                                    <div class="small text-muted">{{ $be->methodLabel() }}@if ($be->sire_name)
                                            , {{ $be->sire_name }}
                                        @endif
                                    </div>
                                </div>
                                <span class="pill {{ $bm['pill'] }}">{{ $bm['label'] }}</span>
                            </div>
                        @empty
                            <div class="text-muted">এই গাভির কোনো প্রজননের রেকর্ড নেই।</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    @endsection
