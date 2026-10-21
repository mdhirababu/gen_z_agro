@php
    $cards = [
        'paid' => [$labels['paid'], 'bi-check-circle'],
        'partial' => [$labels['partial'], 'bi-hourglass-split'],
        'unpaid' => [$labels['unpaid'], 'bi-clock-history'],
    ];
@endphp

<div class="panel h-auto mb-3">
    <div class="panel-head">{{ $title }}</div>
    <div class="panel-body">
        <div class="row g-2 mb-3">
            @foreach ($cards as $key => [$label, $icon])
                <div class="col-md-4">
                    <label class="plan-card" :class="{ 'is-selected': pay === '{{ $key }}' }"
                        style="padding: 0.9rem 1rem">
                        <input type="radio" name="pay_mode" value="{{ $key }}" x-model="pay"
                            class="visually-hidden">
                        <i class="bi bi-check-circle-fill plan-check" x-show="pay === '{{ $key }}'"
                            x-cloak></i>
                        <div class="fw-semibold"><i class="bi {{ $icon }} me-2"></i>{{ $label }}</div>
                    </label>
                </div>
            @endforeach
        </div>

        <div class="row g-2" x-show="pay !== 'unpaid'" x-cloak>
            <div class="col-md-6" x-show="pay === 'partial'">
                <label for="paid_now" class="form-label">{{ $partialLabel }}</label>
                <div class="input-group">
                    <span class="input-group-text">৳</span>
                    <input id="paid_now" type="number" step="0.01" min="0.01" name="paid_now"
                        value="{{ old('paid_now') }}" class="form-control @error('paid_now') is-invalid @enderror">
                </div>
                @error('paid_now')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="pay_method" class="form-label">{{ $methodLabel }}</label>
                <select id="pay_method" name="pay_method" class="form-select">
                    @foreach (config('farm.payment_methods') as $key => $label)
                        <option value="{{ $key }}" @selected(old('pay_method', 'cash') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="small text-muted mt-2" x-show="pay === 'unpaid'" x-cloak>{{ $unpaidHint }}</div>
    </div>
</div>
