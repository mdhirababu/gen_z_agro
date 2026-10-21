@php
    $c = $cow ?? null;
    $pre = $prefill ?? [];

    $val = fn($key, $default = '') => old($key, $c ? $c->{$key} ?? $default : $pre[$key] ?? $default);
    $dateVal = fn($key) => old($key, $c && $c->{$key} ? $c->{$key}->format('Y-m-d') : '');
    $moneyVal = fn($key) => old($key, $c && (float) $c->{$key} > 0 ? $c->{$key} : '');

    $acqNow = old('acquisition_type', $c ? $c->acquisition_type : $pre['acquisition_type'] ?? 'purchased');
    $catNow = old('category', $c ? $c->category : $pre['category'] ?? 'milking');
    $sexNow = old('sex', $c ? $c->sex : 'female');
    $statusNow = old('status', $c ? $c->status : 'active');
    $photoUrl = $c ? $c->photoUrl() : null;
    $acqIcons = ['born' => 'bi-heart', 'purchased' => 'bi-bag-check', 'existing' => 'bi-house-check'];
@endphp

<div x-data="{ acq: @js($acqNow), status: @js($statusNow), preview: @js($photoUrl) }">

    {{-- ===== পরিচয় ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">পরিচয়</div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="field">
                        <label for="tag_number" class="form-label">ট্যাগ নম্বর <span class="text-danger">*</span></label>
                        <div class="field-control">
                            <i class="bi bi-tag lead-ic"></i>
                            <input id="tag_number" type="text" name="tag_number" value="{{ $val('tag_number') }}"
                                class="form-control @error('tag_number') is-invalid @enderror" required>
                        </div>
                        @error('tag_number')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label for="name" class="form-label">নাম (ঐচ্ছিক)</label>
                        <input id="name" type="text" name="name" value="{{ $val('name') }}"
                            class="form-control @error('name') is-invalid @enderror">
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="field">
                        <label for="category" class="form-label">গরুর ধরন <span class="text-danger">*</span></label>
                        <select id="category" name="category"
                            class="form-select @error('category') is-invalid @enderror" required>
                            @foreach (config('farm.categories') as $key => $meta)
                                <option value="{{ $key }}" @selected($catNow === $key)>{{ $meta['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('category')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label class="form-label d-block">লিঙ্গ <span class="text-danger">*</span></label>
                        <div class="btn-group" role="group">
                            @foreach (config('farm.sexes') as $key => $label)
                                <input type="radio" class="btn-check" name="sex" id="sex-{{ $key }}"
                                    value="{{ $key }}" @checked($sexNow === $key)>
                                <label class="btn btn-outline-primary"
                                    for="sex-{{ $key }}">{{ $label }}</label>
                            @endforeach
                        </div>
                        @error('sex')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="field mb-0">
                <label for="breed" class="form-label">জাত</label>
                <select id="breed" name="breed" class="form-select @error('breed') is-invalid @enderror">
                    <option value="">বেছে নিন</option>
                    @foreach (config('farm.breeds') as $key => $label)
                        <option value="{{ $key }}" @selected($val('breed') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('breed')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    {{-- ===== বয়স ও পরিবার ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">বয়স ও পরিবার</div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="field">
                        <label for="birth_date" class="form-label">জন্মতারিখ</label>
                        <input id="birth_date" type="date" name="birth_date" value="{{ $dateVal('birth_date') }}"
                            max="{{ now()->toDateString() }}"
                            class="form-control @error('birth_date') is-invalid @enderror">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="birth_date_estimated" id="est"
                                value="1" @checked(old('birth_date_estimated', $c ? $c->birth_date_estimated : false))>
                            <label class="form-check-label small" for="est">আনুমানিক তারিখ</label>
                        </div>
                        @error('birth_date')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field">
                        <label for="mother_id" class="form-label">মা গরু</label>
                        <select id="mother_id" name="mother_id"
                            class="form-select @error('mother_id') is-invalid @enderror">
                            <option value="">মা অজানা বা খামারে নেই</option>
                            @foreach ($mothers as $m)
                                <option value="{{ $m->id }}" @selected((string) $val('mother_id') === (string) $m->id)>
                                    {{ $m->tag_number }}@if ($m->name)
                                        ({{ $m->name }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('mother_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="field mb-0">
                <label for="sire_name" class="form-label">বাবার ট্যাগ বা নাম (জানা থাকলে)</label>
                <input id="sire_name" type="text" name="sire_name" value="{{ $val('sire_name') }}"
                    class="form-control @error('sire_name') is-invalid @enderror">
            </div>
        </div>
    </div>

    {{-- ===== উৎস ও মূল্য ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">কোথা থেকে এসেছে</div>
        <div class="panel-body">
            <div class="row g-2 mb-3">
                @foreach (config('farm.acquisitions') as $key => $label)
                    <div class="col-md-4">
                        <label class="plan-card" :class="{ 'is-selected': acq === '{{ $key }}' }"
                            style="padding: 0.9rem 1rem">
                            <input type="radio" name="acquisition_type" value="{{ $key }}"
                                x-model="acq" class="visually-hidden">
                            <i class="bi bi-check-circle-fill plan-check" x-show="acq === '{{ $key }}'"
                                x-cloak></i>
                            <div class="fw-semibold"><i
                                    class="bi {{ $acqIcons[$key] ?? 'bi-dot' }} me-2"></i>{{ $label }}</div>
                        </label>
                    </div>
                @endforeach
            </div>
            @error('acquisition_type')
                <div class="field-error mb-2">{{ $message }}</div>
            @enderror

            <div x-show="acq === 'purchased'" x-cloak>
                <div class="row">
                    <div class="col-md-6">
                        <div class="field mb-md-0">
                            <label for="purchase_date" class="form-label">ক্রয়ের তারিখ</label>
                            <input id="purchase_date" type="date" name="purchase_date"
                                value="{{ $dateVal('purchase_date') }}" max="{{ now()->toDateString() }}"
                                class="form-control @error('purchase_date') is-invalid @enderror">
                            @error('purchase_date')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field mb-0">
                            <label for="purchase_price" class="form-label">ক্রয়মূল্য</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input id="purchase_price" type="number" step="0.01" min="0"
                                    name="purchase_price" value="{{ $moneyVal('purchase_price') }}"
                                    class="form-control @error('purchase_price') is-invalid @enderror">
                            </div>
                            @error('purchase_price')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="acq === 'existing'" x-cloak>
                <div class="field mb-0" style="max-width: 20rem">
                    <label for="opening_value" class="form-label">প্রারম্ভিক মূল্য</label>
                    <div class="input-group">
                        <span class="input-group-text">৳</span>
                        <input id="opening_value" type="number" step="0.01" min="0" name="opening_value"
                            value="{{ $moneyVal('opening_value') }}"
                            class="form-control @error('opening_value') is-invalid @enderror">
                    </div>
                    <div class="field-hint">আগে থেকে খামারে থাকলে এখনকার আনুমানিক বাজারদর লিখুন। এটাই শুরুর বিনিয়োগ
                        ধরা হবে।</div>
                    @error('opening_value')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div x-show="acq === 'born'" x-cloak class="small text-muted">
                খামারে জন্ম নিলে ক্রয়মূল্য ধরা হয় না। জন্মের পরের খাবার, চিকিৎসা ইত্যাদি খরচ থেকেই বাছুরের হিসাব শুরু
                হবে।
            </div>
        </div>
    </div>

    {{-- ===== অবস্থা (শুধু সম্পাদনায়) ===== --}}
    @if ($c)
        <div class="panel h-auto mb-3">
            <div class="panel-head">বর্তমান অবস্থা</div>
            <div class="panel-body">
                @if ($c->status === 'sold')
                    <span class="pill gray">বিক্রিত</span>
                    <span class="small text-muted ms-2">বিক্রির লেনদেন থেকে এই অবস্থা এসেছে, এখান থেকে বদলানো যায়
                        না।</span>
                @else
                    <div class="row">
                        <div class="col-md-6">
                            <div class="field mb-md-0">
                                <label for="status" class="form-label">অবস্থা</label>
                                <select id="status" name="status" class="form-select" x-model="status">
                                    <option value="active">সক্রিয়</option>
                                    <option value="dead">মৃত</option>
                                    <option value="transferred">সরানো হয়েছে</option>
                                </select>
                                @error('status')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6" x-show="status !== 'active'" x-cloak>
                            <div class="field mb-0">
                                <label for="exit_date" class="form-label">কবে</label>
                                <input id="exit_date" type="date" name="exit_date"
                                    value="{{ $dateVal('exit_date') }}" max="{{ now()->toDateString() }}"
                                    class="form-control @error('exit_date') is-invalid @enderror">
                                @error('exit_date')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== ছবি ও নোট ===== --}}
    <div class="panel h-auto mb-3">
        <div class="panel-head">ছবি ও নোট</div>
        <div class="panel-body">
            <div class="d-flex flex-wrap gap-3 mb-3">
                <div class="cow-hero-photo" style="width: 9rem; aspect-ratio: 1; font-size: 2rem">
                    <img x-show="preview" :src="preview" alt="গরুর ছবি" x-cloak>
                    <i class="bi bi-image" x-show="!preview"></i>
                </div>
                <div class="flex-grow-1" style="min-width: 14rem">
                    <label for="photo" class="form-label">ছবি (সর্বোচ্চ ৩ মেগাবাইট)</label>
                    <input id="photo" type="file" name="photo" accept="image/*"
                        class="form-control @error('photo') is-invalid @enderror"
                        @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : @js($photoUrl)">
                    @error('photo')
                        <div class="field-error">{{ $message }}</div>
                    @enderror

                    @if ($photoUrl)
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_photo" id="remove_photo"
                                value="1"
                                @change="preview = $event.target.checked ? null : @js($photoUrl)">
                            <label class="form-check-label small" for="remove_photo">বর্তমান ছবিটি সরিয়ে দিন</label>
                        </div>
                    @endif
                </div>
            </div>

            <div class="field mb-0">
                <label for="notes" class="form-label">নোট</label>
                <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ $val('notes') }}</textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4"><i
                class="bi bi-check-lg me-1"></i>{{ $submitLabel }}</button>
        <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">বাতিল</a>
    </div>
</div>
