@php
    $e = $expense ?? null;
    $locked = $e && (float) $e->paid_amount > 0; // পেমেন্ট শুরু হলে অঙ্ক বদলানো যায় না
    $catNow = old('category', $e ? $e->category : 'feed');
    $dateNow = old('expense_date', $e ? $e->expense_date->format('Y-m-d') : now()->toDateString());
    $cowNow = old('cow_id', $e ? $e->cow_id : request('cow'));
    $val = fn($key, $default = '') => old($key, $e ? $e->{$key} ?? $default : $default);
@endphp

<div x-data="{ pay: @js(old('pay_mode', 'paid')) }">

    <div class="panel h-auto mb-3">
        <div class="panel-head">খরচের তথ্য</div>
        <div class="panel-body">

            <div class="field">
                <label class="form-label d-block">খরচের ধরন <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (config('farm.expense_categories') as $key => $meta)
                        <input type="radio" class="btn-check" name="category" id="cat-{{ $key }}"
                            value="{{ $key }}" @checked($catNow === $key)>
                        <label class="btn btn-outline-primary btn-sm" for="cat-{{ $key }}">
                            <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $meta['label'] }}
                        </label>
                    @endforeach
                </div>
                @error('category')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="field">
                        <label for="expense_date" class="form-label">তারিখ <span class="text-danger">*</span></label>
                        <input id="expense_date" type="date" name="expense_date" value="{{ $dateNow }}"
                            max="{{ now()->toDateString() }}"
                            class="form-control @error('expense_date') is-invalid @enderror" required>
                        @error('expense_date')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label for="total_amount" class="form-label">মোট টাকা <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input id="total_amount" type="number" step="0.01" min="0.01" name="total_amount"
                                value="{{ $e ? old('total_amount', $e->total_amount) : old('total_amount') }}"
                                class="form-control @error('total_amount') is-invalid @enderror"
                                @if ($locked) disabled @else required @endif>
                        </div>
                        @if ($locked)
                            <div class="field-hint">পেমেন্ট শুরু হয়েছে, তাই অঙ্ক বদলানো যায় না। বদলাতে হলে খরচটি বাতিল
                                করে নতুন করে যোগ করুন।</div>
                        @endif
                        @error('total_amount')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="description" class="form-label">কীসের খরচ</label>
                <input id="description" type="text" name="description" value="{{ $val('description') }}"
                    class="form-control @error('description') is-invalid @enderror" placeholder="যেমন: ভুসি ১০ বস্তা">
                @error('description')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-6 col-md-3">
                    <div class="field">
                        <label for="quantity" class="form-label">পরিমাণ</label>
                        <input id="quantity" type="number" step="0.01" min="0" name="quantity"
                            value="{{ $e ? old('quantity', (float) $e->quantity ?: '') : old('quantity') }}"
                            class="form-control @error('quantity') is-invalid @enderror">
                        @error('quantity')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="field">
                        <label for="unit" class="form-label">একক</label>
                        <input id="unit" type="text" name="unit" value="{{ $val('unit') }}"
                            class="form-control" placeholder="কেজি, বস্তা">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label for="vendor_name" class="form-label">কার কাছ থেকে</label>
                        <input id="vendor_name" type="text" name="vendor_name" value="{{ $val('vendor_name') }}"
                            class="form-control" placeholder="দোকান বা ব্যক্তির নাম">
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="cow_id" class="form-label">কোন গরুর জন্য</label>
                <select id="cow_id" name="cow_id" class="form-select @error('cow_id') is-invalid @enderror">
                    <option value="">কোনো নির্দিষ্ট গরু নয়, খামারের সাধারণ খরচ</option>
                    @foreach ($cows as $c)
                        <option value="{{ $c->id }}" @selected((string) $cowNow === (string) $c->id)>
                            {{ $c->tag_number }}@if ($c->name)
                                ({{ $c->name }})
                            @endif
                        </option>
                    @endforeach
                </select>
                <div class="field-hint">নির্দিষ্ট গরুর খরচ বেছে নিলে ওই গরুর লাভ-ক্ষতির হিসাবে যুক্ত হবে।</div>
                @error('cow_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field mb-0">
                <label for="notes" class="form-label">নোট</label>
                <textarea id="notes" name="notes" rows="2" class="form-control">{{ $val('notes') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ===== পরিশোধ (শুধু নতুন খরচে) ===== --}}
    @unless ($e)
        <div class="panel h-auto mb-3">
            <div class="panel-head">টাকা দেওয়া হয়েছে কি</div>
            <div class="panel-body">
                <div class="row g-2 mb-3">
                    @foreach (['paid' => ['পুরো টাকা দেওয়া হয়েছে', 'bi-check-circle'], 'partial' => ['কিছু দেওয়া হয়েছে', 'bi-hourglass-split'], 'unpaid' => ['বকেয়া, এখনো দেওয়া হয়নি', 'bi-clock-history']] as $key => [$label, $icon])
                        <div class="col-md-4">
                            <label class="plan-card" :class="{ 'is-selected': pay === '{{ $key }}' }"
                                style="padding: 0.9rem 1rem">
                                <input type="radio" name="pay_mode" value="{{ $key }}" x-model="pay"
                                    class="visually-hidden">
                                <i class="bi bi-check-circle-fill plan-check" x-show="pay === '{{ $key }}'"
                                    x-cloak></i>
                                <div class="fw-semibold"><i class="bi {{ $icon }} me-2"></i>{{ $label }}
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="row g-2" x-show="pay !== 'unpaid'" x-cloak>
                    <div class="col-md-6" x-show="pay === 'partial'">
                        <label for="paid_now" class="form-label">কত টাকা দেওয়া হয়েছে</label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input id="paid_now" type="number" step="0.01" min="0.01" name="paid_now"
                                value="{{ old('paid_now') }}"
                                class="form-control @error('paid_now') is-invalid @enderror">
                        </div>
                        @error('paid_now')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="pay_method" class="form-label">কীভাবে দিয়েছেন</label>
                        <select id="pay_method" name="pay_method" class="form-select">
                            @foreach (config('farm.payment_methods') as $key => $label)
                                <option value="{{ $key }}" @selected(old('pay_method', 'cash') === $key)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="small text-muted mt-2" x-show="pay === 'unpaid'" x-cloak>
                    বকেয়া থাকলে পরে খরচের পাতা থেকে যেকোনো সময় পরিশোধ নথিভুক্ত করতে পারবেন।
                </div>
            </div>
        </div>
    @endunless

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4"><i
                class="bi bi-check-lg me-1"></i>{{ $submitLabel }}</button>
        <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">বাতিল</a>
    </div>
</div>
