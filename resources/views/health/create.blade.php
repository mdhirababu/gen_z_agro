@extends('layouts.farm')
@section('title', 'নতুন স্বাস্থ্য রেকর্ড')

@section('content')
    @php
        $kinds = config('farm.health_kinds');
        $vaccines = config('farm.vaccines');
        $kindNow = old('kind', $kind);
        $codeNow = old('vaccine_code', $vaccine);
        $selectedNow = array_map('intval', (array) old('cow_ids', $selected));
        $intervals = collect($vaccines)->map(fn($v) => $v['interval_days'])->all();
        $catTags = config('farm.categories');
    @endphp

    <div class="page-head">
        <div>
            <h1>নতুন স্বাস্থ্য রেকর্ড</h1>
            <p>এক বা একাধিক গরুর জন্য চিকিৎসা, টিকা, কৃমিনাশক বা পরীক্ষা লিখে রাখুন।</p>
        </div>
        <a href="{{ route('farms.health.index', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>তালিকা
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('farms.health.store', $farm) }}" x-data="{
                kind: @js($kindNow),
                code: @js((string) $codeNow),
                date: @js((string) old('event_date', now()->toDateString())),
                next: @js((string) old('next_due_date', '')),
                auto: @js(!old('next_due_date')),
                picked: @js($selectedNow),
                allIds: @js($cows->pluck('id')->all()),
                q: '',
                cost: @js((string) old('cost_amount', '')),
                pay: @js(old('pay_mode', 'paid')),
                intervals: @js($intervals),
                suggest() {
                    const days = this.kind === 'vaccination' ? this.intervals[this.code] : null;
                    if (!this.auto || !days || !this.date) { return; }
                    const d = new Date(this.date + 'T00:00:00');
                    d.setDate(d.getDate() + days);
                    this.next = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                },
                toggleAll() { this.picked = this.picked.length === this.allIds.length ? [] : [...this.allIds]; }
            }"
                x-init="suggest()">
                @csrf

                {{-- ===== ধরন ===== --}}
                <div class="row g-2 mb-3">
                    @foreach ($kinds as $key => $meta)
                        <div class="col-6 col-md-3">
                            <label class="plan-card" :class="{ 'is-selected': kind === '{{ $key }}' }"
                                style="padding: 0.8rem 0.9rem">
                                <input type="radio" name="kind" value="{{ $key }}" x-model="kind"
                                    @change="suggest()" class="visually-hidden">
                                <i class="bi bi-check-circle-fill plan-check" x-show="kind === '{{ $key }}'"
                                    x-cloak></i>
                                <span class="ic sm {{ $meta['tone'] }} mb-2"><i class="bi {{ $meta['icon'] }}"></i></span>
                                <div class="fw-semibold small">{{ $meta['label'] }}</div>
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('kind')
                    <div class="field-error mb-2">{{ $message }}</div>
                @enderror

                {{-- ===== গরু ===== --}}
                <div class="panel h-auto mb-3">
                    <div class="panel-head d-flex justify-content-between align-items-center">
                        <span>কোন গরু</span>
                        <span class="small fw-normal text-muted"><span x-text="picked.length"></span>টি বাছা হয়েছে</span>
                    </div>
                    <div class="panel-body">
                        @if ($cows->isEmpty())
                            <div class="alert alert-warning mb-0">রেকর্ড করার মতো কোনো সক্রিয় গরু নেই।</div>
                        @else
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <div class="field-control flex-grow-1">
                                    <i class="bi bi-search lead-ic"></i>
                                    <input type="text" class="form-control" placeholder="ট্যাগ বা নাম দিয়ে খুঁজুন"
                                        x-model="q">
                                </div>
                                <button type="button" class="btn btn-outline-secondary" @click="toggleAll()">
                                    <span
                                        x-text="picked.length === allIds.length ? 'সব বাদ দিন' : 'সব সক্রিয় গরু বাছুন'"></span>
                                </button>
                            </div>

                            <div class="pick-list">
                                @foreach ($cows as $cow)
                                    <label class="pick-item"
                                        x-show="q === '' || @js(mb_strtolower($cow->tag_number . ' ' . $cow->name)).includes(q.toLowerCase())">
                                        <input type="checkbox" class="form-check-input m-0" name="cow_ids[]"
                                            :value="{{ $cow->id }}" x-model="picked">
                                        <span class="fw-semibold">{{ $cow->tag_number }}</span>
                                        <span class="small text-muted">{{ $cow->name }}</span>
                                        <span
                                            class="tag {{ $catTags[$cow->category]['tag'] ?? 'gray' }} ms-auto">{{ $catTags[$cow->category]['label'] ?? $cow->category }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="field-hint">একাধিক গরু বাছলে প্রতিটির জন্য আলাদা রেকর্ড হবে। খরচ লিখলে সেটা একবারই
                                ধরা হবে।</div>
                        @endif

                        @foreach ($errors->get('cow_ids.*') as $messages)
                            <div class="field-error">{{ $messages[0] }}</div>
                        @endforeach
                        @error('cow_ids')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- ===== বিবরণ ===== --}}
                <div class="panel h-auto mb-3">
                    <div class="panel-head">বিবরণ</div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="event_date" class="form-label">তারিখ <span
                                            class="text-danger">*</span></label>
                                    <input id="event_date" type="date" name="event_date" x-model="date"
                                        @change="suggest()" max="{{ now()->toDateString() }}"
                                        class="form-control @error('event_date') is-invalid @enderror" required>
                                    @error('event_date')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6" x-show="kind === 'vaccination'" x-cloak>
                                <div class="field">
                                    <label for="vaccine_code" class="form-label">কোন টিকা <span
                                            class="text-danger">*</span></label>
                                    <select id="vaccine_code" name="vaccine_code"
                                        class="form-select @error('vaccine_code') is-invalid @enderror" x-model="code"
                                        @change="suggest()">
                                        @foreach ($vaccines as $key => $v)
                                            <option value="{{ $key }}">{{ $v['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('vaccine_code')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6"
                                x-show="kind === 'treatment' || kind === 'deworming' || kind === 'checkup' || (kind === 'vaccination' && code === 'other')"
                                x-cloak>
                                <div class="field">
                                    <label for="title" class="form-label">
                                        <span
                                            x-text="kind === 'treatment' ? 'রোগ বা সমস্যার নাম' : (kind === 'deworming' ? 'কৃমিনাশকের নাম' : (kind === 'checkup' ? 'পরীক্ষার বিষয়' : 'টিকার নাম'))"></span>
                                        <span class="text-danger"
                                            x-show="kind === 'treatment' || kind === 'vaccination'">*</span>
                                    </label>
                                    <input id="title" type="text" name="title" value="{{ old('title') }}"
                                        class="form-control @error('title') is-invalid @enderror"
                                        placeholder="যেমন: জ্বর, পা ফোলা">
                                    @error('title')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label for="details" class="form-label">কী সমস্যা, কী করা হলো</label>
                            <textarea id="details" name="details" rows="2" class="form-control @error('details') is-invalid @enderror">{{ old('details') }}</textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="medicine" class="form-label">ওষুধ বা টিকার নাম (ব্র্যান্ড)</label>
                                    <input id="medicine" type="text" name="medicine" value="{{ old('medicine') }}"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="vet_name" class="form-label">ডাক্তারের নাম</label>
                                    <input id="vet_name" type="text" name="vet_name" value="{{ old('vet_name') }}"
                                        class="form-control">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6" x-show="kind === 'treatment'" x-cloak>
                                <div class="field">
                                    <label class="form-label d-block">বর্তমান অবস্থা</label>
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="outcome" id="oc-ongoing"
                                            value="ongoing" @checked(old('outcome', 'ongoing') === 'ongoing')>
                                        <label class="btn btn-outline-primary" for="oc-ongoing">চিকিৎসা চলছে</label>
                                        <input type="radio" class="btn-check" name="outcome" id="oc-rec"
                                            value="recovered" @checked(old('outcome') === 'recovered')>
                                        <label class="btn btn-outline-primary" for="oc-rec">সুস্থ</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6" x-show="kind === 'treatment'" x-cloak>
                                <div class="field">
                                    <label for="milk_withdrawal_until" class="form-label">দুধ বিক্রি বন্ধ কত তারিখ
                                        পর্যন্ত</label>
                                    <input id="milk_withdrawal_until" type="date" name="milk_withdrawal_until"
                                        value="{{ old('milk_withdrawal_until') }}"
                                        class="form-control @error('milk_withdrawal_until') is-invalid @enderror">
                                    <div class="field-hint">ওষুধের নির্দেশনা বা ডাক্তারের কথা অনুযায়ী তারিখ দিন। ফাঁকা
                                        রাখলে সতর্কতা আসবে না।</div>
                                    @error('milk_withdrawal_until')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="field mb-0" style="max-width: 20rem">
                            <label for="next_due_date" class="form-label">
                                <span x-text="kind === 'treatment' ? 'পরের পরীক্ষার তারিখ (ঐচ্ছিক)' : 'পরের তারিখ'"></span>
                            </label>
                            <input id="next_due_date" type="date" name="next_due_date" x-model="next"
                                @input="auto = false" class="form-control @error('next_due_date') is-invalid @enderror">
                            <div class="field-hint" x-show="kind === 'vaccination' && intervals[code]" x-cloak>
                                সাধারণ ব্যবধান ধরে বসানো হয়েছে। টিকার নির্দেশনা বা ডাক্তারের কথা অনুযায়ী বদলে নিন।
                            </div>
                            <div class="field-hint">তারিখ কাছে এলে "আসন্ন কাজ"-এ দেখা যাবে।</div>
                            @error('next_due_date')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- ===== খরচ (শুধু মালিক ও ম্যানেজার) ===== --}}
                @if ($canFinance)
                    <div class="panel h-auto mb-3">
                        <div class="panel-head">খরচ (ঐচ্ছিক)</div>
                        <div class="panel-body">
                            <div class="field mb-0" style="max-width: 20rem">
                                <label for="cost_amount" class="form-label">মোট কত টাকা খরচ হলো</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input id="cost_amount" type="number" step="0.01" min="0"
                                        name="cost_amount" x-model="cost"
                                        class="form-control @error('cost_amount') is-invalid @enderror">
                                </div>
                                <div class="field-hint">খরচের পাতায় "চিকিৎসা ও ওষুধ" ধরনে জমা হবে। একাধিক গরু বাছলে সব
                                    গরুর মোট খরচ একবারই ধরা হবে।</div>
                                @error('cost_amount')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div x-show="parseFloat(cost) > 0" x-cloak>
                        @include('finance._pay_mode', [
                            'title' => 'টাকা দেওয়া হয়েছে কি',
                            'labels' => [
                                'paid' => 'পুরো টাকা দেওয়া হয়েছে',
                                'partial' => 'কিছু দেওয়া হয়েছে',
                                'unpaid' => 'বকেয়া, এখনো দেওয়া হয়নি',
                            ],
                            'partialLabel' => 'কত টাকা দেওয়া হয়েছে',
                            'methodLabel' => 'কীভাবে দিয়েছেন',
                            'unpaidHint' => 'বকেয়া থাকলে পরে খরচের পাতা থেকে পরিশোধ নথিভুক্ত করতে পারবেন।',
                        ])
                    </div>
                @endif

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>সংরক্ষণ
                        করুন</button>
                    <a href="{{ route('farms.health.index', $farm) }}" class="btn btn-outline-secondary">বাতিল</a>
                </div>
            </form>
        </div>

        <div class="col-lg-4 mt-3 mt-lg-0">
            <div class="panel h-auto">
                <div class="panel-head">জেনে রাখুন</div>
                <div class="panel-body">
                    <ul class="todo-list">
                        <li><i class="bi bi-people"></i><span>টিকার দিন পুরো পাল একসঙ্গে বাছুন, প্রতিটি গরুর আলাদা রেকর্ড
                                হবে।</span></li>
                        <li><i class="bi bi-arrow-repeat"></i><span>একই গরুকে একই টিকা আবার দিলে আগের "পরের তারিখ" নিজে
                                থেকে সম্পন্ন হবে।</span></li>
                        <li><i class="bi bi-droplet-half"></i><span>দুধ বিক্রি বন্ধের তারিখ দিলে সেই গরুর দুধ এন্ট্রির সময়
                                সতর্কতা দেখাবে।</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
