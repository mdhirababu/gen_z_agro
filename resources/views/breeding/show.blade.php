@extends('layouts.farm')
@section('title', 'প্রজননের বিবরণ')

@section('content')
    @php
        $st = $event->statusMeta();
        $today = now()->startOfDay();
        $dayText = fn(?int $d) => $d === null
            ? '—'
            : ($d < 0
                ? abs($d) . ' দিন দেরি'
                : ($d === 0
                    ? 'আজ'
                    : $d . ' দিন পরে'));

        $toCalving = $event->expected_calving_date
            ? (int) $today->diffInDays($event->expected_calving_date->copy()->startOfDay(), false)
            : null;
        $toCheck = $event->next_check_date
            ? (int) $today->diffInDays($event->next_check_date->copy()->startOfDay(), false)
            : null;

        $elapsed = (int) $event->service_date->copy()->startOfDay()->diffInDays($today, false);
        $span = $event->expected_calving_date
            ? max(
                1,
                (int) $event->service_date
                    ->copy()
                    ->startOfDay()
                    ->diffInDays($event->expected_calving_date->copy()->startOfDay(), false),
            )
            : (int) config('farm.breeding.gestation_days');
        $percent = max(0, min(100, (int) round(($elapsed / $span) * 100)));

        $open = in_array($event->status, ['served', 'pregnant'], true);
        $cowActive = $event->cow && $event->cow->status === 'active';
        $dam = $event->cow;
    @endphp

    <div x-data="{ del: { open: false } }" @keydown.escape.window="del.open = false">

        <div class="page-head">
            <div class="d-flex align-items-center gap-3">
                <span class="ic rose"><i class="bi bi-hearts"></i></span>
                <div>
                    <h1>
                        @if ($dam && !$dam->trashed())
                            <a href="{{ route('farms.cows.show', [$farm, $dam]) }}"
                                class="text-reset">{{ $dam->tag_number }}</a>
                        @else
                            {{ $dam ? $dam->tag_number : 'প্রজনন' }}
                        @endif
                        <span class="pill {{ $st['pill'] }} fs-6 ms-1">{{ $st['label'] }}</span>
                    </h1>
                    <p>{{ $event->methodLabel() }}, {{ $event->service_date->format('d/m/Y') }}@if ($event->sire_name)
                            , {{ $event->sire_name }}
                        @endif
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('farms.breeding.index', $farm) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>তালিকা
                </a>
                @if ($canManage)
                    <a href="{{ route('farms.breeding.edit', [$farm, $event]) }}" class="icon-btn" title="সম্পাদনা করুন"
                        aria-label="সম্পাদনা করুন"><i class="bi bi-pencil"></i></a>
                    <button type="button" class="icon-btn danger" title="রেকর্ড সরান" aria-label="রেকর্ড সরান"
                        @click="del.open = true"><i class="bi bi-trash3"></i></button>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach (collect($errors->all())->take(4) as $message)
                    <div>{{ $message }}</div>
                @endforeach
            </div>
        @endif

        {{-- ===== মূল তারিখ ===== --}}
        <div class="row g-2 mb-3">
            <div class="col-6 col-lg-3">
                <div class="tile sky">
                    <div class="tile-label">সেবার তারিখ</div>
                    <div class="tile-value">{{ $event->service_date->format('d/m/Y') }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="tile mint">
                    <div class="tile-label">গর্ভ পরীক্ষা</div>
                    <div class="tile-value">
                        @if ($event->status === 'served' && $event->next_check_date)
                            {{ $event->next_check_date->format('d/m/Y') }}
                        @elseif ($event->checked_on)
                            {{ $event->checked_on->format('d/m/Y') }}
                        @else
                            —
                        @endif
                    </div>
                    @if ($event->status === 'served')
                        <div class="small text-muted">{{ $dayText($toCheck) }}</div>
                    @endif
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="tile peach">
                    <div class="tile-label">প্রসবের সম্ভাব্য তারিখ</div>
                    <div class="tile-value">
                        {{ $event->expected_calving_date ? $event->expected_calving_date->format('d/m/Y') : '—' }}</div>
                    @if ($event->status === 'pregnant')
                        <div class="small text-muted">{{ $dayText($toCalving) }}</div>
                    @endif
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="tile lilac">
                    <div class="tile-label">
                        @if ($event->status === 'calved')
                            প্রসব হয়েছে
                        @elseif ($event->status === 'aborted')
                            গর্ভপাত
                        @elseif ($event->status === 'open')
                            গর্ভ হয়নি নিশ্চিত
                        @else
                            ফল
                        @endif
                    </div>
                    <div class="tile-value">{{ $event->outcome_date ? $event->outcome_date->format('d/m/Y') : '—' }}</div>
                </div>
            </div>
        </div>

        @if ($event->status === 'pregnant')
            <div class="panel h-auto mb-3">
                <div class="panel-body">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>গর্ভের {{ max(0, $elapsed) }}তম দিন</span>
                        <span
                            class="text-muted">{{ $toCalving !== null && $toCalving >= 0 ? 'প্রসবের আর ' . $toCalving . ' দিন' : 'প্রসবের সম্ভাব্য সময় পেরিয়ে গেছে' }}</span>
                    </div>
                    <div class="usage-bar {{ $toCalving !== null && $toCalving < 0 ? 'is-full' : '' }}"><span
                            style="width: {{ $percent }}%"></span></div>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">

                {{-- ===== গর্ভ পরীক্ষার ফল ===== --}}
                @if ($open)
                    <div class="panel h-auto mb-3">
                        <div class="panel-head">
                            {{ $event->status === 'served' ? 'গর্ভ পরীক্ষার ফল' : 'পরীক্ষার ফল বদলান' }}</div>
                        <div class="panel-body">
                            <form method="POST" action="{{ route('farms.breeding.check', [$farm, $event]) }}">
                                @csrf
                                <div class="field">
                                    <label class="form-label d-block">ফল</label>
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="result" id="r-preg"
                                            value="pregnant" @checked(old('result', 'pregnant') === 'pregnant')>
                                        <label class="btn btn-outline-primary" for="r-preg">গর্ভবতী</label>
                                        <input type="radio" class="btn-check" name="result" id="r-open" value="open"
                                            @checked(old('result') === 'open')>
                                        <label class="btn btn-outline-primary" for="r-open">গর্ভ হয়নি</label>
                                    </div>
                                    @error('result')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="field">
                                            <label for="checked_on" class="form-label">পরীক্ষার তারিখ</label>
                                            <input id="checked_on" type="date" name="checked_on"
                                                value="{{ old('checked_on', now()->toDateString()) }}"
                                                max="{{ now()->toDateString() }}"
                                                class="form-control @error('checked_on') is-invalid @enderror" required>
                                            @error('checked_on')
                                                <div class="field-error">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="field">
                                            <label for="expected_calving_date" class="form-label">প্রসবের সম্ভাব্য
                                                তারিখ</label>
                                            <input id="expected_calving_date" type="date" name="expected_calving_date"
                                                value="{{ old('expected_calving_date', $event->expected_calving_date ? $event->expected_calving_date->format('Y-m-d') : '') }}"
                                                class="form-control @error('expected_calving_date') is-invalid @enderror">
                                            @error('expected_calving_date')
                                                <div class="field-error">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>ফল সংরক্ষণ করুন</button>
                            </form>
                        </div>
                    </div>

                    {{-- ===== প্রসব ===== --}}
                    @if ($cowActive)
                        <div class="panel h-auto mb-3">
                            <div class="panel-head">প্রসব নথিভুক্ত করুন</div>
                            <div class="panel-body">
                                <form method="POST" action="{{ route('farms.breeding.calve', [$farm, $event]) }}"
                                    x-data="{ calves: @js(old('calves', [['tag_number' => '', 'sex' => 'female', 'name' => '', 'weight_kg' => '', 'alive' => '1']])) }">
                                    @csrf

                                    <div class="field" style="max-width: 16rem">
                                        <label for="calved_on" class="form-label">প্রসবের তারিখ</label>
                                        <input id="calved_on" type="date" name="calved_on"
                                            value="{{ old('calved_on', now()->toDateString()) }}"
                                            max="{{ now()->toDateString() }}"
                                            class="form-control @error('calved_on') is-invalid @enderror" required>
                                        @error('calved_on')
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <template x-for="(c, i) in calves" :key="i">
                                        <div class="border rounded-3 p-3 mb-2">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong x-text="'বাছুর ' + (i + 1)"></strong>
                                                <button type="button" class="icon-btn sm danger"
                                                    x-show="calves.length > 1" @click="calves.splice(i, 1)"
                                                    title="এই সারি বাদ দিন" aria-label="এই সারি বাদ দিন"><i
                                                        class="bi bi-x-lg"></i></button>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">ট্যাগ নম্বর *</label>
                                                    <input type="text" class="form-control"
                                                        :name="'calves[' + i + '][tag_number]'" x-model="c.tag_number"
                                                        required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">লিঙ্গ</label>
                                                    <select class="form-select" :name="'calves[' + i + '][sex]'"
                                                        x-model="c.sex">
                                                        <option value="female">মাদি</option>
                                                        <option value="male">নর</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">জন্মের ওজন (কেজি)</label>
                                                    <input type="number" step="0.1" min="0"
                                                        class="form-control" :name="'calves[' + i + '][weight_kg]'"
                                                        x-model="c.weight_kg">
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label small mb-1">নাম (ঐচ্ছিক)</label>
                                                    <input type="text" class="form-control"
                                                        :name="'calves[' + i + '][name]'" x-model="c.name">
                                                </div>
                                                <div class="col-md-4 d-flex align-items-end">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="1"
                                                            :name="'calves[' + i + '][alive]'" x-model="c.alive"
                                                            :id="'alive-' + i">
                                                        <label class="form-check-label" :for="'alive-' + i">জীবিত
                                                            জন্মেছে</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    @foreach ($errors->get('calves.*') as $messages)
                                        <div class="field-error">{{ $messages[0] }}</div>
                                    @endforeach
                                    @error('calves')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror

                                    <div class="mb-3">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            x-show="calves.length < 3"
                                            @click="calves.push({ tag_number: '', sex: 'female', name: '', weight_kg: '', alive: true })">
                                            <i class="bi bi-plus-lg me-1"></i>আরেকটি বাছুর (যমজ)
                                        </button>
                                    </div>

                                    @if (in_array($dam->category, ['heifer', 'dry'], true))
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="update_dam"
                                                id="update_dam" value="1" checked>
                                            <label class="form-check-label" for="update_dam">মা গরুর ধরন "দুধ দিচ্ছে"
                                                করুন</label>
                                        </div>
                                    @endif

                                    <div class="small text-muted mb-3">
                                        জীবিত বাছুর প্ল্যানের গরুর সীমায় গোনা হয়
                                        @if ($slotsLeft !== null)
                                            (এখন আর {{ $slotsLeft }}টির জায়গা আছে)
                                        @endif।
                                        মৃত বাছুর "মৃত" অবস্থায় রেকর্ড হয়, জায়গা নেয় না।
                                    </div>

                                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>প্রসব ও বাছুর
                                        সংরক্ষণ করুন</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- ===== গর্ভপাত ===== --}}
                    @if ($event->status === 'pregnant')
                        <details class="panel h-auto mb-3">
                            <summary class="panel-head" style="cursor: pointer">গর্ভপাত হয়েছে? এখানে নথিভুক্ত করুন
                            </summary>
                            <div class="panel-body">
                                <form method="POST" action="{{ route('farms.breeding.abort', [$farm, $event]) }}"
                                    class="d-flex flex-wrap gap-2 align-items-end">
                                    @csrf
                                    <div>
                                        <label for="outcome_date" class="form-label">গর্ভপাতের তারিখ</label>
                                        <input id="outcome_date" type="date" name="outcome_date"
                                            value="{{ old('outcome_date', now()->toDateString()) }}"
                                            max="{{ now()->toDateString() }}"
                                            class="form-control @error('outcome_date') is-invalid @enderror" required>
                                        @error('outcome_date')
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <button class="btn btn-outline-danger">গর্ভপাত নথিভুক্ত করুন</button>
                                </form>
                            </div>
                        </details>
                    @endif
                @endif

                {{-- ===== প্রসবে জন্মানো বাছুর ===== --}}
                @if ($event->status === 'calved')
                    <div class="panel h-auto mb-3">
                        <div class="panel-head">এই প্রসবের বাছুর</div>
                        <div class="panel-body">
                            @forelse ($calves as $calf)
                                <div
                                    class="d-flex justify-content-between align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <div>
                                        <a class="fw-semibold"
                                            href="{{ route('farms.cows.show', [$farm, $calf]) }}">{{ $calf->tag_number }}</a>
                                        @if ($calf->name)
                                            <span class="text-muted small">{{ $calf->name }}</span>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-1">
                                        <span class="tag blue">{{ config('farm.sexes')[$calf->sex] ?? $calf->sex }}</span>
                                        <span
                                            class="tag {{ $calf->status === 'dead' ? 'red' : 'green' }}">{{ $calf->status === 'dead' ? 'মৃত' : 'সক্রিয়' }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">বাছুরের তথ্য পাওয়া যায়নি।</div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-5">
                <div class="panel h-auto">
                    <div class="panel-head">বিবরণ</div>
                    <div class="panel-body">
                        <dl class="dl-grid" style="grid-template-columns: 1fr">
                            <div>
                                <dt>পদ্ধতি</dt>
                                <dd>{{ $event->methodLabel() }}</dd>
                            </div>
                            <div>
                                <dt>বীজ বা ষাঁড়</dt>
                                <dd>{{ $event->sire_name ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt>কে করেছেন</dt>
                                <dd>{{ $event->technician ?: '—' }}</dd>
                            </div>
                            @if ($event->notes)
                                <div>
                                    <dt>নোট</dt>
                                    <dd>{{ $event->notes }}</dd>
                                </div>
                            @endif
                            @if ($canFinance && $event->expense)
                                <div>
                                    <dt>খরচ</dt>
                                    <dd>
                                        <a
                                            href="{{ route('farms.expenses.show', [$farm, $event->expense]) }}">{{ \App\Support\Money::fmt($event->expense->total_amount) }}</a>
                                        @if ($event->expense->isVoid())
                                            <span class="pill gray">বাতিল</span>
                                        @endif
                                    </dd>
                                </div>
                            @endif
                            <div>
                                <dt>নথিভুক্ত করেছেন</dt>
                                <dd>{{ $event->creator ? $event->creator->name : '—' }},
                                    {{ $event->created_at->format('d/m/Y') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== সরানোর পপ-আপ ===== --}}
        @if ($canManage)
            <div class="sheet-backdrop" x-show="del.open" x-transition.opacity style="display: none"
                @click.self="del.open = false">
                <div class="sheet" role="dialog" aria-modal="true">
                    <form method="POST" action="{{ route('farms.breeding.destroy', [$farm, $event]) }}">
                        @csrf
                        @method('DELETE')
                        <div class="sheet-head">
                            <span class="ic rose"><i class="bi bi-trash3"></i></span>
                            <div>
                                <h3>রেকর্ডটি সরাবেন?</h3>
                                <p>{{ $dam ? $dam->tag_number : '' }}, {{ $event->service_date->format('d/m/Y') }}</p>
                            </div>
                        </div>
                        <p class="sheet-note mb-0">সেবার রেকর্ডটি তালিকা থেকে সরে যাবে। প্রসবে তৈরি হওয়া বাছুর বা জুড়ে
                            থাকা খরচ বদলাবে না।</p>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-outline-secondary" @click="del.open = false">ফিরে
                                যান</button>
                            <button type="submit" class="btn btn-danger">হ্যাঁ, সরান</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
