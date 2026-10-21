@extends('layouts.farm')
@section('title', 'নতুন বিক্রি')

@section('content')
    @php
        $types = config('farm.sale_types');
        $typeNow = old('sale_type', $type);
    @endphp

    <div class="page-head">
        <div>
            <h1>নতুন বিক্রি নথিভুক্ত করুন</h1>
            <p>কী বিক্রি করলেন, কত টাকায়, আর টাকা পেয়েছেন কি না।</p>
        </div>
        <a href="{{ route('farms.sales.index', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>তালিকা
        </a>
    </div>

    @if ($errors->has('cow_id') && old('sale_type') === 'cow')
        <div class="alert alert-danger">{{ $errors->first('cow_id') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('farms.sales.store', $farm) }}" x-data="{
                type: @js($typeNow),
                qty: @js((string) old('quantity', '')),
                price: @js((string) old('unit_price', '')),
                total: @js((string) old('total_amount', '')),
                cow: @js((string) old('cow_id', $cowId)),
                pay: @js(old('pay_mode', 'paid')),
                cows: @js($cowInfo),
                get computed() {
                    if (this.type === 'milk' || this.type === 'manure') {
                        return (parseFloat(this.qty) || 0) * (parseFloat(this.price) || 0);
                    }
                    return parseFloat(this.total) || 0;
                },
                get invested() { return this.cows[this.cow] ? this.cows[this.cow].invested : null; },
                fmt(n) { return '৳ ' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n); }
            }">
                @csrf

                {{-- ===== ধরন ===== --}}
                <div class="row g-2 mb-3">
                    @foreach ($types as $key => $meta)
                        <div class="col-6 col-md-3">
                            <label class="plan-card" :class="{ 'is-selected': type === '{{ $key }}' }"
                                style="padding: 0.8rem 0.9rem">
                                <input type="radio" name="sale_type" value="{{ $key }}" x-model="type"
                                    class="visually-hidden">
                                <i class="bi bi-check-circle-fill plan-check" x-show="type === '{{ $key }}'"
                                    x-cloak></i>
                                <span class="ic sm {{ $meta['tone'] }} mb-2"><i class="bi {{ $meta['icon'] }}"></i></span>
                                <div class="fw-semibold small">{{ $meta['label'] }}</div>
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('sale_type')
                    <div class="field-error mb-2">{{ $message }}</div>
                @enderror

                {{-- ===== বিক্রির তথ্য ===== --}}
                <div class="panel h-auto mb-3">
                    <div class="panel-head">বিক্রির তথ্য</div>
                    <div class="panel-body">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="sold_at" class="form-label">তারিখ <span
                                            class="text-danger">*</span></label>
                                    <input id="sold_at" type="date" name="sold_at"
                                        value="{{ old('sold_at', now()->toDateString()) }}"
                                        max="{{ now()->toDateString() }}"
                                        class="form-control @error('sold_at') is-invalid @enderror" required>
                                    @error('sold_at')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="customer_id" class="form-label">ক্রেতা</label>
                                    <select id="customer_id" name="customer_id"
                                        class="form-select @error('customer_id') is-invalid @enderror">
                                        <option value="">নগদ বিক্রি, ক্রেতার নাম নেই</option>
                                        @foreach ($customers as $c)
                                            <option value="{{ $c->id }}" @selected((string) old('customer_id') === (string) $c->id)>
                                                {{ $c->name }}@if ($c->phone)
                                                    ({{ $c->phone }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="field-hint">
                                        বকেয়া রাখলে ক্রেতা বাছতেই হবে।
                                        <a href="{{ route('farms.customers.index', $farm) }}" target="_blank">নতুন ক্রেতা
                                            যোগ করুন</a>
                                    </div>
                                    @error('customer_id')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- দুধ ও গোবর: পরিমাণ × দর --}}
                        <div x-show="type === 'milk' || type === 'manure'" x-cloak>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="field">
                                        <label for="quantity" class="form-label">
                                            পরিমাণ (<span x-text="type === 'milk' ? 'লিটার' : 'কেজি'"></span>)
                                        </label>
                                        <input id="quantity" type="number" step="0.01" min="0" name="quantity"
                                            x-model="qty" class="form-control @error('quantity') is-invalid @enderror">
                                        @error('quantity')
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="field">
                                        <label for="unit_price" class="form-label">
                                            দর (প্রতি <span x-text="type === 'milk' ? 'লিটার' : 'কেজি'"></span>)
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">৳</span>
                                            <input id="unit_price" type="number" step="0.01" min="0"
                                                name="unit_price" x-model="price"
                                                class="form-control @error('unit_price') is-invalid @enderror">
                                        </div>
                                        @error('unit_price')
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="tile mint mb-3">
                                <div class="tile-label">মোট টাকা (নিজে থেকে হিসাব হচ্ছে)</div>
                                <div class="tile-value" x-text="fmt(computed)"></div>
                            </div>
                        </div>

                        {{-- গরু বিক্রি --}}
                        <div x-show="type === 'cow'" x-cloak>
                            <div class="field">
                                <label for="cow_id" class="form-label">কোন গরু</label>
                                <select id="cow_id" name="cow_id"
                                    class="form-select @error('cow_id') is-invalid @enderror" x-model="cow">
                                    <option value="">গরু বেছে নিন</option>
                                    @foreach ($cows as $c)
                                        <option value="{{ $c->id }}">{{ $c->tag_number }}@if ($c->name)
                                                ({{ $c->name }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('cow_id')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                                @if ($cows->isEmpty())
                                    <div class="field-hint">বিক্রির মতো কোনো সক্রিয় গরু নেই।</div>
                                @endif
                            </div>

                            <div class="tile sand mb-3" x-show="cow && invested !== null" x-cloak>
                                <div class="tile-label">এই গরুর মোট বিনিয়োগ (ভিত্তিমূল্য ও সরাসরি খরচ)</div>
                                <div class="tile-value" x-text="fmt(invested)"></div>
                                <div class="small mt-1" x-show="computed > 0">
                                    আনুমানিক ফলাফল:
                                    <strong :class="computed - invested >= 0 ? 'text-success' : 'text-danger'"
                                        x-text="(computed - invested >= 0 ? 'লাভ ' : 'ক্ষতি ') + fmt(Math.abs(computed - invested))"></strong>
                                    <span class="text-muted">(যৌথ খরচ ধরা হয়নি)</span>
                                </div>
                            </div>
                        </div>

                        {{-- গরু ও অন্যান্য আয়: লেখা মোট টাকা --}}
                        <div x-show="type === 'cow' || type === 'other_income'" x-cloak>
                            <div class="field">
                                <label for="total_amount" class="form-label">মোট টাকা</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input id="total_amount" type="number" step="0.01" min="0"
                                        name="total_amount" x-model="total"
                                        class="form-control @error('total_amount') is-invalid @enderror">
                                </div>
                                @error('total_amount')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="field">
                            <label for="description" class="form-label">
                                বিবরণ <span x-show="type === 'other_income'" class="text-danger">*</span>
                            </label>
                            <input id="description" type="text" name="description" value="{{ old('description') }}"
                                class="form-control @error('description') is-invalid @enderror"
                                placeholder="যেমন: সরকারি ভর্তুকি, খামার পরিদর্শনের ফি">
                            @error('description')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="field mb-0">
                            <label for="notes" class="form-label">নোট</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                @include('finance._pay_mode', [
                    'title' => 'টাকা পেয়েছেন কি',
                    'labels' => [
                        'paid' => 'পুরো টাকা পেয়েছি',
                        'partial' => 'কিছু টাকা পেয়েছি',
                        'unpaid' => 'বকেয়া, এখনো পাইনি',
                    ],
                    'partialLabel' => 'কত টাকা পেয়েছেন',
                    'methodLabel' => 'কীভাবে পেয়েছেন',
                    'unpaidHint' => 'বকেয়া থাকলে পরে ক্রেতার পাতা থেকে টাকা আদায় নথিভুক্ত করতে পারবেন।',
                ])

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>বিক্রি সংরক্ষণ
                        করুন</button>
                    <a href="{{ route('farms.sales.index', $farm) }}" class="btn btn-outline-secondary">বাতিল</a>
                </div>
            </form>
        </div>

        <div class="col-lg-4 mt-3 mt-lg-0">
            <div class="panel h-auto">
                <div class="panel-head">জেনে রাখুন</div>
                <div class="panel-body">
                    <ul class="todo-list">
                        <li><i class="bi bi-calculator"></i><span>দুধ ও গোবরে মোট টাকা পরিমাণ × দর থেকে নিজে থেকে হিসাব
                                হয়।</span></li>
                        <li><i class="bi bi-collection"></i><span>গরু বিক্রি নথিভুক্ত করলে গরুটি "বিক্রিত" হয়ে যাবে এবং
                                প্ল্যানের গরুর জায়গা ফাঁকা হবে।</span></li>
                        <li><i class="bi bi-slash-circle"></i><span>ভুল হলে বিক্রিটি বাতিল করে নতুন করে যোগ করুন। গরু
                                বিক্রি বাতিল করলে গরুটি আবার সক্রিয় হবে।</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
