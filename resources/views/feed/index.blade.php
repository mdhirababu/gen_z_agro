@extends('layouts.farm')
@section('title', 'খাবার')

@section('content')
    @php
        $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.') ?: '0';
        $units = config('farm.feed_units');
        $cats = config('farm.categories');
        $typeUnits = $activeTypes->mapWithKeys(fn($t) => [$t->id => $units[$t->unit] ?? $t->unit])->all();
    @endphp

    <div x-data="{ del: { open: false, url: '', title: '', sub: '' } }" @keydown.escape.window="del.open = false">
        <div class="page-head">
            <div>
                <h1>খাবার</h1>
                <p>কোন দিন কোন খাবার কতটুকু দেওয়া হলো।</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form method="GET" action="{{ route('farms.feed.index', $farm) }}">
                    <select name="m" class="form-select" onchange="this.form.submit()" aria-label="মাস">
                        @foreach ($months as $m)
                            <option value="{{ $m }}" @selected($month === $m)>
                                {{ substr($m, 5) }}/{{ substr($m, 0, 4) }}</option>
                        @endforeach
                    </select>
                </form>
                @if ($canManage)
                    <a href="{{ route('farms.feed.types', $farm) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list-ul me-1"></i>খাবারের ধরন
                    </a>
                @endif
            </div>
        </div>

        <div class="row g-3">
            {{-- ===== নতুন রেকর্ড ===== --}}
            <div class="col-lg-4">
                <div class="panel h-auto">
                    <div class="panel-head">খাবারের রেকর্ড যোগ করুন</div>
                    <div class="panel-body">
                        @if ($activeTypes->isEmpty())
                            <div class="alert alert-warning mb-0">
                                কোনো খাবারের ধরন নেই.
                                @if ($canManage)
                                    <a href="{{ route('farms.feed.types', $farm) }}">আগে ধরন যোগ করুন</a>।
                                @else
                                    ম্যানেজার বা মালিককে ধরন যোগ করতে বলুন।
                                @endif
                            </div>
                        @else
                            <form method="POST" action="{{ route('farms.feed.store', $farm) }}" x-data="{ typeId: @js((string) old('feed_type_id', '')), target: @js(old('target', 'all')), units: @js($typeUnits) }">
                                @csrf

                                <div class="field">
                                    <label for="fed_on" class="form-label">তারিখ</label>
                                    <input id="fed_on" type="date" name="fed_on"
                                        value="{{ old('fed_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                                        class="form-control @error('fed_on') is-invalid @enderror" required>
                                    @error('fed_on')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label for="feed_type_id" class="form-label">কোন খাবার</label>
                                    <select id="feed_type_id" name="feed_type_id"
                                        class="form-select @error('feed_type_id') is-invalid @enderror" x-model="typeId"
                                        required>
                                        <option value="">বেছে নিন</option>
                                        @foreach ($activeTypes as $t)
                                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('feed_type_id')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label for="quantity" class="form-label">কতটুকু</label>
                                    <div class="input-group">
                                        <input id="quantity" type="number" step="0.01" min="0" name="quantity"
                                            value="{{ old('quantity') }}"
                                            class="form-control @error('quantity') is-invalid @enderror" required>
                                        <span class="input-group-text" x-text="units[typeId] || 'একক'"></span>
                                    </div>
                                    @error('quantity')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label class="form-label d-block">কাদের দেওয়া হলো</label>
                                    <div class="btn-group" role="group">
                                        @foreach (config('farm.feed_targets') as $key => $label)
                                            <input type="radio" class="btn-check" name="target"
                                                id="t-{{ $key }}" value="{{ $key }}" x-model="target">
                                            <label class="btn btn-outline-primary btn-sm"
                                                for="t-{{ $key }}">{{ $label }}</label>
                                        @endforeach
                                    </div>
                                    @error('target')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="field" x-show="target === 'category'" x-cloak>
                                    <label for="category" class="form-label">কোন ধরনের গরু</label>
                                    <select id="category" name="category"
                                        class="form-select @error('category') is-invalid @enderror"
                                        :disabled="target !== 'category'">
                                        @foreach ($cats as $key => $meta)
                                            <option value="{{ $key }}" @selected(old('category') === $key)>
                                                {{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('category')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="field" x-show="target === 'cow'" x-cloak>
                                    <label for="cow_id" class="form-label">কোন গরু</label>
                                    <select id="cow_id" name="cow_id"
                                        class="form-select @error('cow_id') is-invalid @enderror"
                                        :disabled="target !== 'cow'">
                                        @foreach ($cows as $c)
                                            <option value="{{ $c->id }}" @selected((string) old('cow_id') === (string) $c->id)>
                                                {{ $c->tag_number }}@if ($c->name)
                                                    ({{ $c->name }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('cow_id')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label for="note" class="form-label">নোট (ঐচ্ছিক)</label>
                                    <input id="note" type="text" name="note" value="{{ old('note') }}"
                                        class="form-control">
                                </div>

                                <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>রেকর্ড যোগ
                                    করুন</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===== মাসের হিসাব ও তালিকা ===== --}}
            <div class="col-lg-8">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="kpi">
                            <div class="kpi-label">এই মাসের রেকর্ড</div>
                            <div class="kpi-value">{{ $recordCount }}টি</div>
                        </div>
                    </div>
                    @if ($canFinance)
                        <div class="col-md-6">
                            <div class="kpi peach">
                                <div class="kpi-label">আনুমানিক খাবারের খরচ</div>
                                <div class="kpi-value">{{ \App\Support\Money::fmt($estimated) }}</div>
                                <div class="kpi-note">পরিমাণ × ধরনের দাম, শুধু তথ্যের জন্য। আসল খরচ "খরচ" পাতায়।</div>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($byType->isNotEmpty())
                    <div class="panel h-auto mb-3">
                        <div class="panel-head">এই মাসে কোন খাবার কত</div>
                        <div class="panel-body py-2">
                            @foreach ($byType as $row)
                                @php $t = $types->get($row->feed_type_id); @endphp
                                <div class="pay-row-item">
                                    <div>
                                        <div class="fw-semibold">{{ $t ? $t->name : '—' }}</div>
                                        <div class="small text-muted">{{ $row->records }}টি রেকর্ড</div>
                                    </div>
                                    <div class="fw-semibold">{{ $fmt($row->total) }}
                                        {{ $units[$row->unit] ?? $row->unit }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($records->isEmpty())
                    <div class="panel h-auto">
                        <div class="panel-body empty-state">
                            <i class="bi bi-basket"></i>
                            <h2>এই মাসে কোনো খাবারের রেকর্ড নেই</h2>
                            <p>বাঁ দিকের ফর্ম থেকে আজকের খাবার লিখে শুরু করুন।</p>
                        </div>
                    </div>
                @else
                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>তারিখ</th>
                                        <th>খাবার</th>
                                        <th class="text-end">পরিমাণ</th>
                                        <th>কাদের</th>
                                        <th class="text-end">প্রতি গরু</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($records as $r)
                                        <tr>
                                            <td class="text-nowrap">{{ $r->fed_on->format('d/m/Y') }}</td>
                                            <td>
                                                <span
                                                    class="fw-semibold">{{ $r->feedType ? $r->feedType->name : '—' }}</span>
                                                @if ($r->note)
                                                    <div class="small text-muted">{{ $r->note }}</div>
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">{{ $fmt($r->quantity) }}
                                                {{ $r->unitLabel() }}</td>
                                            <td>
                                                {{ $r->targetLabel() }}
                                                <div class="small text-muted">{{ $r->head_count }}টি গরু</div>
                                            </td>
                                            <td class="text-end text-nowrap">{{ $fmt($r->perHead()) }}
                                                {{ $r->unitLabel() }}</td>
                                            <td class="text-end">
                                                @if ($canManage)
                                                    <button type="button" class="icon-btn sm danger" title="রেকর্ড সরান"
                                                        aria-label="রেকর্ড সরান"
                                                        @click="del = Object.assign(@js([
    'url' => route('farms.feed.destroy', [$farm, $r]),
    'title' => 'রেকর্ডটি সরাবেন?',
    'sub' => ($r->feedType ? $r->feedType->name : '') . ', ' . $r->fed_on->format('d/m/Y'),
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

                    <div class="mt-3">{{ $records->links() }}</div>
                @endif
            </div>
        </div>

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
                    <p class="sheet-note mb-0">ভুল এন্ট্রি হলে সরিয়ে আবার সঠিকটা যোগ করুন। এটা কোনো খরচের হিসাব বদলায় না।
                    </p>
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
