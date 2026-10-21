@extends('layouts.farm')
@section('title', 'পেমেন্ট যাচাই')

@section('content')
    @php
        $statusMap = [
            'pending' => ['label' => 'যাচাইয়ের অপেক্ষায়', 'pill' => 'orange'],
            'approved' => ['label' => 'অনুমোদিত', 'pill' => 'green'],
            'rejected' => ['label' => 'বাতিল', 'pill' => 'red'],
        ];
        $methodMap = [
            'bkash' => ['label' => 'bKash', 'color' => 'rose', 'icon' => 'bi-wallet2'],
            'nagad' => ['label' => 'Nagad', 'color' => 'peach', 'icon' => 'bi-wallet2'],
            'rocket' => ['label' => 'Rocket', 'color' => 'lilac', 'icon' => 'bi-wallet2'],
            'bank' => ['label' => 'Bank', 'color' => 'sky', 'icon' => 'bi-bank'],
        ];

        $count = fn($s) => $summary->get($s) ? (int) $summary->get($s)->total : 0;
        $sum = fn($s) => $summary->get($s) ? (float) $summary->get($s)->amount : 0;
        $all = $count('pending') + $count('approved') + $count('rejected');

        $tabs = [
            '' => ['সব', $all],
            'pending' => ['যাচাইয়ের অপেক্ষায়', $count('pending')],
            'approved' => ['অনুমোদিত', $count('approved')],
            'rejected' => ['বাতিল', $count('rejected')],
        ];
    @endphp

    <div x-data="{
        open: false,
        mode: 'approve',
        p: {},
        review(mode, el) { this.mode = mode;
            this.p = { ...el.dataset };
            this.open = true; }
    }" @keydown.escape.window="open = false">

        <div class="page-head">
            <div>
                <h1>পেমেন্ট যাচাই</h1>
                <p>bKash/Nagad অ্যাপে TrxID ও টাকার পরিমাণ মিলিয়ে তারপর অনুমোদন দিন।</p>
            </div>
        </div>

        {{-- ===== সারসংক্ষেপ ===== --}}
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="kpi peach">
                    <div class="kpi-label">যাচাইয়ের অপেক্ষায়</div>
                    <div class="kpi-value">{{ $count('pending') }}টি</div>
                    <div class="kpi-note">মোট ৳ {{ number_format($sum('pending'), 0) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi">
                    <div class="kpi-label">অনুমোদিত</div>
                    <div class="kpi-value">{{ $count('approved') }}টি</div>
                    <div class="kpi-note">মোট ৳ {{ number_format($sum('approved'), 0) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi rose">
                    <div class="kpi-label">বাতিল</div>
                    <div class="kpi-value">{{ $count('rejected') }}টি</div>
                    <div class="kpi-note">মোট ৳ {{ number_format($sum('rejected'), 0) }}</div>
                </div>
            </div>
        </div>

        {{-- ===== ফিল্টার ===== --}}
        <div class="tab-chips">
            @foreach ($tabs as $key => [$label, $total])
                <a href="{{ route('admin.payments.index', $key === '' ? [] : ['status' => $key]) }}"
                    class="tab-chip {{ ($status ?? '') === (string) $key ? 'active' : '' }}">
                    {{ $label }} <span class="count">{{ $total }}</span>
                </a>
            @endforeach
        </div>

        {{-- ===== তালিকা ===== --}}
        @if ($payments->isEmpty())
            <div class="panel">
                <div class="panel-body empty-state">
                    <i class="bi bi-cash-coin"></i>
                    @if ($status)
                        <h2>এই অবস্থায় কোনো পেমেন্ট নেই</h2>
                        <p>অন্য একটা ফিল্টার বেছে দেখুন।</p>
                        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-primary">সব পেমেন্ট দেখুন</a>
                    @else
                        <h2>এখনো কোনো পেমেন্ট জমা পড়েনি</h2>
                        <p>কোনো খামারি সাবস্ক্রিপশনের পেমেন্ট জমা দিলে এখানে দেখা যাবে।</p>
                    @endif
                </div>
            </div>
        @else
            <div class="panel pay-list h-auto">
                @foreach ($payments as $payment)
                    @php
                        $m = $methodMap[$payment->method] ?? [
                            'label' => $payment->method,
                            'color' => 'sand',
                            'icon' => 'bi-cash',
                        ];
                        $s = $statusMap[$payment->status] ?? ['label' => $payment->status, 'pill' => 'gray'];
                        $farmName = $payment->farm ? $payment->farm->name : '—';
                    @endphp

                    <div class="pay-row {{ $payment->status === 'pending' ? 'is-pending' : '' }}"
                        data-farm="{{ $farmName }}" data-plan="{{ $payment->plan->name }}"
                        data-amount="{{ number_format($payment->amount, 0) }}" data-method="{{ $m['label'] }}"
                        data-trx="{{ $payment->trx_id ?: '—' }}"
                        data-approve-url="{{ route('admin.payments.approve', $payment) }}"
                        data-reject-url="{{ route('admin.payments.reject', $payment) }}">

                        {{-- খামার ও প্ল্যান --}}
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <span class="ic {{ $m['color'] }}"><i class="bi {{ $m['icon'] }}"></i></span>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">
                                    {{ $farmName }}
                                    @if ($payment->farm && $payment->farm->trashed())
                                        <span class="badge bg-secondary ms-1">মুছে ফেলা</span>
                                    @endif
                                </div>
                                <div class="mt-1">
                                    <span class="tag gray">{{ $payment->plan->name }}</span>
                                    <span
                                        class="small text-muted ms-1">{{ $payment->created_at->format('d/m/Y, H:i') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- টাকা --}}
                        <div>
                            <div class="pay-amount">৳ {{ number_format($payment->amount, 0) }}</div>
                        </div>

                        {{-- TrxID ও প্রেরক --}}
                        <div class="min-w-0">
                            <div class="small text-muted">{{ $m['label'] }} TrxID</div>
                            <div class="d-flex align-items-center gap-1" x-data="{ copied: false }">
                                <code>{{ $payment->trx_id ?: '—' }}</code>
                                @if ($payment->trx_id)
                                    <button type="button" class="copy-btn" title="কপি করুন"
                                        @click="navigator.clipboard && navigator.clipboard.writeText(@js($payment->trx_id)); copied = true; setTimeout(() => copied = false, 1500)">
                                        <i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i>
                                    </button>
                                @endif
                            </div>
                            <div class="small text-muted">প্রেরক: {{ $payment->sender_number ?: '—' }}</div>
                        </div>

                        {{-- অবস্থা --}}
                        <div>
                            <span class="pill {{ $s['pill'] }}">{{ $s['label'] }}</span>
                            @if ($payment->status !== 'pending' && $payment->reviewed_at)
                                <div class="small text-muted mt-1">
                                    {{ $payment->reviewed_at->format('d/m/Y') }}
                                    @if ($payment->reviewer)
                                        , {{ $payment->reviewer->name }}
                                    @endif
                                </div>
                            @endif
                            @if ($payment->note)
                                <div class="small text-muted mt-1">কারণ: {{ $payment->note }}</div>
                            @endif
                        </div>

                        {{-- কাজ --}}
                        <div class="pay-actions">
                            @if ($payment->status === 'pending')
                                <button type="button" class="btn btn-sm btn-primary"
                                    @click="review('approve', $el.closest('.pay-row'))">
                                    <i class="bi bi-check-lg me-1"></i>অনুমোদন
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                    @click="review('reject', $el.closest('.pay-row'))">
                                    <i class="bi bi-x-lg me-1"></i>বাতিল
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3">{{ $payments->links() }}</div>
        @endif

        {{-- ===== নিশ্চিতকরণের পপ-আপ ===== --}}
        <div class="sheet-backdrop" x-show="open" x-transition.opacity style="display: none" @click.self="open = false">
            <div class="sheet" role="dialog" aria-modal="true">
                <form method="POST" :action="mode === 'approve' ? p.approveUrl : p.rejectUrl">
                    @csrf

                    <div class="sheet-head">
                        <span class="ic" :class="mode === 'approve' ? 'mint' : 'rose'">
                            <i class="bi" :class="mode === 'approve' ? 'bi-check2-circle' : 'bi-x-circle'"></i>
                        </span>
                        <div>
                            <h3 x-text="mode === 'approve' ? 'পেমেন্ট অনুমোদন করবেন?' : 'পেমেন্ট বাতিল করবেন?'"></h3>
                            <p x-text="p.farm"></p>
                        </div>
                    </div>

                    <dl class="sheet-facts">
                        <div>
                            <dt>প্ল্যান</dt>
                            <dd x-text="p.plan"></dd>
                        </div>
                        <div>
                            <dt>টাকা</dt>
                            <dd x-text="'৳ ' + p.amount"></dd>
                        </div>
                        <div>
                            <dt>মাধ্যম</dt>
                            <dd x-text="p.method"></dd>
                        </div>
                        <div>
                            <dt>TrxID</dt>
                            <dd><code x-text="p.trx"></code></dd>
                        </div>
                    </dl>

                    <p class="sheet-note" x-show="mode === 'approve'">
                        অ্যাপে এই TrxID ও টাকার পরিমাণ মিলিয়ে দেখেছেন তো? অনুমোদন দিলে খামারের সাবস্ক্রিপশন সঙ্গে সঙ্গে
                        চালু হয়ে যাবে।
                    </p>

                    <div x-show="mode === 'reject'">
                        <label for="reject-note" class="form-label">বাতিলের কারণ (ঐচ্ছিক)</label>
                        <textarea id="reject-note" name="note" rows="2" class="form-control" :disabled="mode !== 'reject'"
                            placeholder="যেমন: TrxID মেলেনি"></textarea>
                        <div class="field-hint">কারণ লিখে রাখলে পরে মনে রাখা সহজ হবে।</div>
                    </div>

                    <div class="sheet-actions">
                        <button type="button" class="btn btn-outline-secondary" @click="open = false">ফিরে যান</button>
                        <button type="submit" class="btn" :class="mode === 'approve' ? 'btn-primary' : 'btn-danger'"
                            x-text="mode === 'approve' ? 'হ্যাঁ, অনুমোদন দিন' : 'হ্যাঁ, বাতিল করুন'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
