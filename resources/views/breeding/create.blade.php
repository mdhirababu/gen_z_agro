@extends('layouts.farm')
@section('title', 'নতুন প্রজনন সেবা')

@section('content')
    @php
        $methods = config('farm.breeding_methods');
        $selectedNow = (int) old('cow_id', $selected);
        $catTags = config('farm.categories');
    @endphp

    <div class="page-head">
        <div>
            <h1>নতুন প্রজনন সেবা</h1>
            <p>কোন গাভিকে, কবে, কীভাবে সেবা দেওয়া হলো, লিখে রাখুন।</p>
        </div>
        <a href="{{ route('farms.breeding.index', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>তালিকা
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('farms.breeding.store', $farm) }}" x-data="{
                date: @js((string) old('service_date', now()->toDateString())),
                method: @js(old('method', 'ai')),
                check: @js((string) old('next_check_date', '')),
                calve: @js((string) old('expected_calving_date', '')),
                autoCheck: @js(!old('next_check_date')),
                autoCalve: @js(!old('expected_calving_date')),
                cost: @js((string) old('cost_amount', '')),
                pay: @js(old('pay_mode', 'paid')),
                checkDays: {{ (int) config('farm.breeding.check_after_days') }},
                gestDays: {{ (int) config('farm.breeding.gestation_days') }},
                add(n) {
                    if (!this.date) { return ''; }
                    const d = new Date(this.date + 'T00:00:00');
                    d.setDate(d.getDate() + n);
                    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                },
                suggest() {
                    if (this.autoCheck) { this.check = this.add(this.checkDays); }
                    if (this.autoCalve) { this.calve = this.add(this.gestDays); }
                }
            }"
                x-init="suggest()">
                @csrf

                <div class="panel h-auto mb-3">
                    <div class="panel-head">সেবার তথ্য</div>
                    <div class="panel-body">

                        <div class="field">
                            <label for="cow_id" class="form-label">কোন গাভি <span class="text-danger">*</span></label>
                            <select id="cow_id" name="cow_id" class="form-select @error('cow_id') is-invalid @enderror"
                                required>
                                <option value="">গাভি বেছে নিন</option>
                                @foreach ($cows as $cow)
                                    @php $isPregnant = in_array($cow->id, $pregnantIds); @endphp
                                    <option value="{{ $cow->id }}" @selected($selectedNow === $cow->id)
                                        @disabled($isPregnant)>
                                        {{ $cow->tag_number }}@if ($cow->name)
                                            ({{ $cow->name }})
                                        @endif,
                                        {{ $catTags[$cow->category]['label'] ?? $cow->category }}@if ($isPregnant)
                                            , গর্ভবতী
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="field-hint">শুধু সক্রিয় প্রাপ্তবয়স্ক মাদি গরু। গর্ভবতী গরু বাছা যায় না।</div>
                            @error('cow_id')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="service_date" class="form-label">সেবার তারিখ <span
                                            class="text-danger">*</span></label>
                                    <input id="service_date" type="date" name="service_date" x-model="date"
                                        @change="suggest()" max="{{ now()->toDateString() }}"
                                        class="form-control @error('service_date') is-invalid @enderror" required>
                                    @error('service_date')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label class="form-label d-block">পদ্ধতি <span class="text-danger">*</span></label>
                                    <div class="btn-group" role="group">
                                        @foreach ($methods as $key => $label)
                                            <input type="radio" class="btn-check" name="method"
                                                id="m-{{ $key }}" value="{{ $key }}" x-model="method">
                                            <label class="btn btn-outline-primary"
                                                for="m-{{ $key }}">{{ $label }}</label>
                                        @endforeach
                                    </div>
                                    @error('method')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="sire_name" class="form-label">
                                        <span
                                            x-text="method === 'ai' ? 'কোন বীজ (জাত বা ষাঁড়ের নাম)' : 'কোন ষাঁড় (ট্যাগ বা নাম)'"></span>
                                    </label>
                                    <input id="sire_name" type="text" name="sire_name" value="{{ old('sire_name') }}"
                                        class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="technician" class="form-label">কে করলেন</label>
                                    <input id="technician" type="text" name="technician" value="{{ old('technician') }}"
                                        class="form-control" placeholder="কৃত্রিম প্রজননকর্মী বা ডাক্তারের নাম">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="next_check_date" class="form-label">গর্ভ পরীক্ষার তারিখ</label>
                                    <input id="next_check_date" type="date" name="next_check_date" x-model="check"
                                        @input="autoCheck = false"
                                        class="form-control @error('next_check_date') is-invalid @enderror">
                                    <div class="field-hint">সাধারণ ব্যবধান ধরে বসেছে। পশু চিকিৎসকের কথা অনুযায়ী বদলে নিন।
                                    </div>
                                    @error('next_check_date')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="expected_calving_date" class="form-label">প্রসবের সম্ভাব্য তারিখ</label>
                                    <input id="expected_calving_date" type="date" name="expected_calving_date"
                                        x-model="calve" @input="autoCalve = false"
                                        class="form-control @error('expected_calving_date') is-invalid @enderror">
                                    <div class="field-hint">গর্ভ নিশ্চিত হওয়ার পর কাজে লাগবে। তখন আবার বদলানো যাবে।</div>
                                    @error('expected_calving_date')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="field mb-0">
                            <label for="notes" class="form-label">নোট</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- ===== খরচ (শুধু মালিক ও ম্যানেজার) ===== --}}
                @if ($canFinance)
                    <div class="panel h-auto mb-3">
                        <div class="panel-head">খরচ (ঐচ্ছিক)</div>
                        <div class="panel-body">
                            <div class="field mb-0" style="max-width: 20rem">
                                <label for="cost_amount" class="form-label">সেবার জন্য কত টাকা খরচ হলো</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input id="cost_amount" type="number" step="0.01" min="0"
                                        name="cost_amount" x-model="cost"
                                        class="form-control @error('cost_amount') is-invalid @enderror">
                                </div>
                                <div class="field-hint">খরচের পাতায় "প্রজনন" ধরনে জমা হবে।</div>
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
                    <a href="{{ route('farms.breeding.index', $farm) }}" class="btn btn-outline-secondary">বাতিল</a>
                </div>
            </form>
        </div>

        <div class="col-lg-4 mt-3 mt-lg-0">
            <div class="panel h-auto">
                <div class="panel-head">এরপর কী হবে</div>
                <div class="panel-body">
                    <ul class="todo-list">
                        <li><i class="bi bi-bell"></i><span>গর্ভ পরীক্ষার তারিখ কাছে এলে "আসন্ন কাজ"-এ দেখা যাবে।</span>
                        </li>
                        <li><i class="bi bi-clipboard-pulse"></i><span>ফল জানলে সেবার পাতায় "গর্ভবতী" বা "গর্ভ হয়নি"
                                লিখুন।</span></li>
                        <li><i class="bi bi-heart"></i><span>প্রসব হলে একই পাতা থেকে বাছুরের তথ্য দিলে বাছুর গরুর তালিকায়
                                নিজে থেকে যোগ হবে।</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
