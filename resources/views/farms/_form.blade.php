<div class="field">
    <label for="name" class="form-label">খামারের নাম <span class="text-danger">*</span></label>
    <div class="field-control">
        <i class="bi bi-buildings lead-ic"></i>
        <input id="name" type="text" name="name" value="{{ old('name', $farm->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror" required>
    </div>
    @error('name')
        <div class="field-error">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="field">
            <label for="phone" class="form-label">ফোন</label>
            <div class="field-control">
                <i class="bi bi-telephone lead-ic"></i>
                <input id="phone" type="text" name="phone" value="{{ old('phone', $farm->phone ?? '') }}"
                    class="form-control @error('phone') is-invalid @enderror" placeholder="01XXXXXXXXX">
            </div>
            @error('phone')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="field">
            <label for="district" class="form-label">জেলা</label>
            <div class="field-control">
                <i class="bi bi-geo-alt lead-ic"></i>
                <input id="district" type="text" name="district"
                    value="{{ old('district', $farm->district ?? '') }}"
                    class="form-control @error('district') is-invalid @enderror">
            </div>
            @error('district')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="field">
    <label for="address" class="form-label">ঠিকানা</label>
    <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror"
        placeholder="গ্রাম, উপজেলা, ডাকঘর">{{ old('address', $farm->address ?? '') }}</textarea>
    @error('address')
        <div class="field-error">{{ $message }}</div>
    @enderror
</div>
