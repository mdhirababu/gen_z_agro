@php
    $out = ($direction ?? 'out') === 'out';
    $word = $out ? 'পরিশোধ' : 'আদায়';
    $due = $record->dueAmount();
    $canAdd = !$record->isVoid() && $due > 0;
@endphp

<div class="panel h-auto">
    <div class="panel-head">{{ $word }}ের হিসাব</div>
    <div class="panel-body">

        @forelse ($payments as $p)
            <div class="pay-row-item {{ $p->isVoid() ? 'is-void' : '' }}">
                <div>
                    <div class="fw-semibold amt">{{ \App\Support\Money::fmt($p->amount) }}</div>
                    <div class="small text-muted">
                        {{ $p->paid_at->format('d/m/Y') }}, {{ $p->methodLabel() }}
                        @if ($p->note)
                            , {{ $p->note }}
                        @endif
                    </div>
                    @if ($p->isVoid() && $p->void_reason)
                        <div class="small text-muted">কারণ: {{ $p->void_reason }}</div>
                    @endif
                </div>

                @if ($p->isVoid())
                    <span class="pill gray">বাতিল</span>
                @elseif (!$record->isVoid())
                    <button type="button" class="icon-btn sm danger" title="এই পেমেন্ট বাতিল করুন"
                        aria-label="এই পেমেন্ট বাতিল করুন"
                        @click="vm = Object.assign(@js([
                                'url' => route('farms.payments.void', [$farm, $p]),
                                'title' => 'পেমেন্টটি বাতিল করবেন?',
                                'sub' => \App\Support\Money::fmt($p->amount) . ', ' . $p->paid_at->format('d/m/Y'),
                            ]), { open: true })">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                @endif
            </div>
        @empty
            <div class="text-muted">এখনো কোনো {{ $word }} নথিভুক্ত হয়নি।</div>
        @endforelse

        @if ($canAdd)
            <form method="POST" action="{{ $storeUrl }}" class="mt-3 pt-3 border-top">
                @csrf
                <div class="d-flex justify-content-between small mb-2">
                    <span class="fw-semibold">নতুন {{ $word }}</span>
                    <span class="text-muted">বকেয়া: <strong
                            class="text-body">{{ \App\Support\Money::fmt($due) }}</strong></span>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label for="pay-amount" class="form-label small mb-1">পরিমাণ</label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input id="pay-amount" type="number" step="0.01" min="0.01"
                                max="{{ $due }}" name="amount" value="{{ old('amount', $due) }}"
                                class="form-control @error('amount') is-invalid @enderror" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <label for="pay-date" class="form-label small mb-1">তারিখ</label>
                        <input id="pay-date" type="date" name="paid_at"
                            value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                            class="form-control @error('paid_at') is-invalid @enderror" required>
                    </div>
                    <div class="col-6">
                        <label for="pay-method" class="form-label small mb-1">মাধ্যম</label>
                        <select id="pay-method" name="method" class="form-select">
                            @foreach (config('farm.payment_methods') as $key => $label)
                                <option value="{{ $key }}" @selected(old('method', 'cash') === $key)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="pay-note" class="form-label small mb-1">নোট (ঐচ্ছিক)</label>
                        <input id="pay-note" type="text" name="note" value="{{ old('note') }}"
                            class="form-control">
                    </div>
                </div>

                @error('amount')
                    <div class="field-error">{{ $message }}</div>
                @enderror
                @error('paid_at')
                    <div class="field-error">{{ $message }}</div>
                @enderror

                <button class="btn btn-primary w-100 mt-3">
                    <i class="bi bi-check-lg me-1"></i>{{ $word }} নথিভুক্ত করুন
                </button>
            </form>
        @endif
    </div>
</div>
