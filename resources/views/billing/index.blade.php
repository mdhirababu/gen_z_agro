@extends('layouts.farm')
@section('title', 'সাবস্ক্রিপশন')

@section('content')
    @php
        $payTo = config('subscription.pay_to', []);
        $methodLabels = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'bank' => 'Bank'];
        $statusMap = [
            'pending' => ['label' => 'যাচাইয়ের অপেক্ষায়', 'pill' => 'orange'],
            'approved' => ['label' => 'অনুমোদিত', 'pill' => 'green'],
            'rejected' => ['label' => 'বাতিল', 'pill' => 'red'],
        ];
        $pillColors = ['info' => 'blue', 'success' => 'green', 'warning' => 'orange', 'danger' => 'red'];

        $status = $subscription->currentStatus();
        $end = $subscription->endDate();
        $days = $subscription->daysLeft();
        $maxMembers = $subscription->plan->max_members;
        $percent = $maxMembers ? min(100, round(($memberCount / $maxMembers) * 100)) : 0;

        $first = $plans->first();
        $defaultPlan = $plans->contains('id', $subscription->plan_id)
            ? $subscription->plan_id
            : ($first
                ? $first->id
                : '');
        $planPrices = $plans->mapWithKeys(fn($p) => [(string) $p->id => number_format($p->price, 0)])->all();
        $firstMethod = array_key_first($payTo) ?: 'bkash';
    @endphp

    <div class="page-head">
        <div>
            <h1>সাবস্ক্রিপশন</h1>
            <p>বর্তমান প্ল্যান দেখুন, নবায়ন করুন বা প্ল্যান বদলান।</p>
        </div>
    </div>

    {{-- ===== বর্তমান অবস্থা ===== --}}
    <div class="panel h-auto mb-4">
        <div class="panel-body">
            <div class="row g-3 align-items-center">
                <div class="col-lg-7 d-flex align-items-center gap-3">
                    <span class="ic mint" style="width: 3.4rem; height: 3.4rem; font-size: 1.5rem"><i
                            class="bi bi-award"></i></span>
                    <div>
                        <div class="small text-muted">বর্তমান প্ল্যান</div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="fs-4 fw-semibold">{{ $subscription->plan->name }}</span>
                            <span
                                class="pill {{ $pillColors[$subscription->badgeClass()] ?? 'gray' }}">{{ $subscription->statusLabel() }}</span>
                        </div>
                        @if ($end)
                            <div class="small text-muted">
                                মেয়াদ {{ $end->format('d/m/Y') }} পর্যন্ত
                                @if ($days !== null && in_array($status, ['trial', 'active'], true))
                                    ({{ $days }} দিন বাকি)
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">সদস্য</span>
                        <span class="fw-semibold">{{ $memberCount }} / {{ $maxMembers ?? 'সীমাহীন' }}</span>
                    </div>
                    @if ($maxMembers)
                        <div class="usage-bar {{ $percent >= 100 ? 'is-full' : '' }}"><span
                                style="width: {{ $percent }}%"></span></div>
                    @endif
                    <div class="small text-muted mt-2">
                        গরুর সীমা: {{ $subscription->plan->max_cows ? $subscription->plan->max_cows . 'টি' : 'সীমাহীন' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($plans->isEmpty())
        <div class="alert alert-warning">এই মুহূর্তে নতুন করে নেওয়ার মতো কোনো প্ল্যান চালু নেই।</div>
    @else
        <div x-data="{
            plan: '{{ old('plan_id', $defaultPlan) }}',
            method: '{{ old('method', $firstMethod) }}',
            prices: @js($planPrices),
            numbers: @js($payTo),
            copied: false
        }">
            <form method="POST" action="{{ route('farms.billing.store', $farm) }}">
                @csrf
                <fieldset class="border-0 p-0 m-0 min-w-0" @disabled($hasPending)>

                    <h5 class="mb-3">প্ল্যান বেছে নিন</h5>
                    <div class="row g-3 mb-4">
                        @foreach ($plans as $plan)
                            <div class="col-md-6">
                                <label class="plan-card" :class="{ 'is-selected': plan === '{{ $plan->id }}' }">
                                    <input type="radio" name="plan_id" value="{{ $plan->id }}" x-model="plan"
                                        class="visually-hidden">
                                    <i class="bi bi-check-circle-fill plan-check" x-show="plan === '{{ $plan->id }}'"
                                        style="display: none"></i>

                                    <div class="plan-name">
                                        {{ $plan->name }}
                                        @if ($plan->id === $subscription->plan_id)
                                            <span class="pill green ms-1">বর্তমান</span>
                                        @endif
                                    </div>
                                    <div class="plan-price">৳ {{ number_format($plan->price, 0) }} <small>/
                                            {{ $plan->duration_days }} দিন</small></div>

                                    <ul class="plan-feats">
                                        <li class="yes"><i
                                                class="bi bi-check-lg"></i>{{ $plan->max_cows ? $plan->max_cows . 'টি পর্যন্ত গরু' : 'সীমাহীন গরু' }}
                                        </li>
                                        <li class="yes"><i
                                                class="bi bi-check-lg"></i>{{ $plan->max_members ? $plan->max_members . ' জন পর্যন্ত সদস্য' : 'সীমাহীন সদস্য' }}
                                        </li>
                                        <li class="{{ $plan->allows('export') ? 'yes' : 'no' }}">
                                            <i
                                                class="bi {{ $plan->allows('export') ? 'bi-check-lg' : 'bi-x-lg' }}"></i>Excel/PDF
                                            Export
                                        </li>
                                        <li class="{{ $plan->allows('cow_profit_report') ? 'yes' : 'no' }}">
                                            <i
                                                class="bi {{ $plan->allows('cow_profit_report') ? 'bi-check-lg' : 'bi-x-lg' }}"></i>গরুভিত্তিক
                                            লাভ-ক্ষতি রিপোর্ট
                                        </li>
                                    </ul>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('plan_id')
                        <div class="field-error mb-3">{{ $message }}</div>
                    @enderror

                    <div class="row g-3">
                        {{-- ===== পেমেন্ট ===== --}}
                        <div class="col-lg-8">
                            <div class="panel h-auto">
                                <div class="panel-head">পেমেন্ট করুন</div>
                                <div class="panel-body">

                                    @if ($hasPending)
                                        <div class="alert alert-warning mb-3">
                                            <i class="bi bi-hourglass-split me-1"></i>
                                            একটি পেমেন্ট যাচাইয়ের অপেক্ষায় আছে। যাচাই শেষ হলে সাবস্ক্রিপশন চালু বা নবায়ন
                                            হয়ে যাবে।
                                        </div>
                                    @endif

                                    @if (empty($payTo))
                                        <div class="alert alert-warning mb-0">পেমেন্টের মাধ্যম এখনো ঠিক করা হয়নি।
                                            অ্যাডমিনের সাথে যোগাযোগ করুন।</div>
                                    @else
                                        <div class="step">
                                            <span class="step-no">১</span>
                                            <div class="flex-grow-1 min-w-0">
                                                <div class="step-title">
                                                    টাকা পাঠান: <span class="text-success">৳ <span
                                                            x-text="prices[plan] || '—'"></span></span>
                                                </div>
                                                <div class="small text-muted mb-2">যে মাধ্যমে পাঠাবেন সেটা বেছে নিন, তারপর
                                                    নিচের নম্বরে Send Money করুন।</div>

                                                <div class="tab-chips mb-0">
                                                    @foreach ($payTo as $key => $number)
                                                        <label class="tab-chip"
                                                            :class="{ active: method === '{{ $key }}' }"
                                                            style="cursor: pointer">
                                                            <input type="radio" name="method"
                                                                value="{{ $key }}" x-model="method"
                                                                class="visually-hidden">
                                                            {{ $methodLabels[$key] ?? $key }}
                                                        </label>
                                                    @endforeach
                                                </div>

                                                <div class="pay-number">
                                                    <span>নম্বর: <b x-text="numbers[method]"></b></span>
                                                    <button type="button" class="copy-btn" title="নম্বর কপি করুন"
                                                        aria-label="নম্বর কপি করুন"
                                                        @click="navigator.clipboard && navigator.clipboard.writeText(numbers[method]); copied = true; setTimeout(() => copied = false, 1500)">
                                                        <i class="bi"
                                                            :class="copied ? 'bi-check2' : 'bi-clipboard'"></i>
                                                    </button>
                                                </div>
                                                @error('method')
                                                    <div class="field-error">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="step">
                                            <span class="step-no">২</span>
                                            <div class="flex-grow-1">
                                                <div class="step-title mb-2">TrxID ও প্রেরকের নম্বর লিখুন</div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label for="trx_id" class="form-label small">TrxID <span
                                                                class="text-danger">*</span></label>
                                                        <input id="trx_id" type="text" name="trx_id"
                                                            value="{{ old('trx_id') }}"
                                                            class="form-control @error('trx_id') is-invalid @enderror"
                                                            required>
                                                        @error('trx_id')
                                                            <div class="field-error">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="sender_number" class="form-label small">যে নম্বর থেকে
                                                            পাঠিয়েছেন</label>
                                                        <input id="sender_number" type="text" name="sender_number"
                                                            value="{{ old('sender_number') }}"
                                                            class="form-control @error('sender_number') is-invalid @enderror">
                                                        @error('sender_number')
                                                            <div class="field-error">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="step">
                                            <span class="step-no">৩</span>
                                            <div class="flex-grow-1">
                                                <div class="step-title mb-2">জমা দিন</div>
                                                @if ($hasPending)
                                                    <div class="small text-muted">আগের পেমেন্টটি যাচাই না হওয়া পর্যন্ত
                                                        নতুন পেমেন্ট জমা দেওয়া যাবে না।</div>
                                                @else
                                                    <button type="submit" class="btn btn-primary px-4">
                                                        <i class="bi bi-send me-1"></i>পেমেন্ট জমা দিন
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- ===== পরে কী হবে ===== --}}
                        <div class="col-lg-4">
                            <div class="panel h-auto">
                                <div class="panel-head">জমা দেওয়ার পর কী হবে</div>
                                <div class="panel-body">
                                    <ul class="todo-list">
                                        <li><i class="bi bi-search"></i><span>অ্যাডমিন আপনার TrxID ও টাকার পরিমাণ মিলিয়ে
                                                দেখবেন।</span></li>
                                        <li><i class="bi bi-check2-circle"></i><span>অনুমোদন হলে সাবস্ক্রিপশন সঙ্গে সঙ্গে
                                                চালু বা নবায়ন হবে।</span></li>
                                        <li><i class="bi bi-calendar-plus"></i><span>একই প্ল্যানে মেয়াদ থাকতে নবায়ন করলে
                                                বাকি দিনগুলো যোগ হয়ে যায়।</span></li>
                                        <li><i class="bi bi-shield-check"></i><span>মেয়াদ শেষ হলেও ডেটা মুছে যায় না,
                                                খামার শুধু দেখা যায়।</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
    @endif

    {{-- ===== পেমেন্টের ইতিহাস ===== --}}
    <h5 class="mt-4 mb-3">পেমেন্টের ইতিহাস</h5>

    @if ($payments->isEmpty())
        <div class="panel h-auto">
            <div class="panel-body empty-state">
                <i class="bi bi-receipt"></i>
                <h2>এখনো কোনো পেমেন্ট নেই</h2>
                <p>পেমেন্ট জমা দিলে এখানে তার অবস্থা দেখা যাবে।</p>
            </div>
        </div>
    @else
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>তারিখ</th>
                            <th>প্ল্যান</th>
                            <th class="text-end">টাকা</th>
                            <th>মাধ্যম</th>
                            <th>TrxID</th>
                            <th>অবস্থা</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            @php $s = $statusMap[$payment->status] ?? ['label' => $payment->status, 'pill' => 'gray']; @endphp
                            <tr>
                                <td class="text-nowrap">{{ $payment->created_at->format('d/m/Y') }}</td>
                                <td>{{ $payment->plan->name }}</td>
                                <td class="text-end fw-semibold text-nowrap">৳ {{ number_format($payment->amount, 0) }}
                                </td>
                                <td><span
                                        class="tag blue">{{ $methodLabels[$payment->method] ?? $payment->method }}</span>
                                </td>
                                <td><code>{{ $payment->trx_id }}</code></td>
                                <td>
                                    <span class="pill {{ $s['pill'] }}">{{ $s['label'] }}</span>
                                    @if ($payment->note)
                                        <div class="small text-muted mt-1">কারণ: {{ $payment->note }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
