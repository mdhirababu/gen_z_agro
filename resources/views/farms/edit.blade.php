@extends('layouts.farm')
@section('title', 'খামারের সেটিংস')

@section('content')
    <div x-data="{ open: false, typed: '', name: @js($farm->name) }" @keydown.escape.window="open = false">

        <div class="page-head">
            <div>
                <h1>খামারের সেটিংস</h1>
                <p>{{ $farm->name }}-এর তথ্য বদলান।</p>
            </div>
            <a href="{{ route('farms.dashboard', $farm) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>ড্যাশবোর্ডে ফিরুন
            </a>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <form method="POST" action="{{ route('farms.update', $farm) }}">
                    @csrf
                    @method('PUT')
                    <div class="panel h-auto mb-3">
                        <div class="panel-head">খামারের তথ্য</div>
                        <div class="panel-body">
                            @include('farms._form')

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-check-lg me-1"></i>আপডেট করুন
                                </button>
                                <a href="{{ route('farms.dashboard', $farm) }}" class="btn btn-outline-secondary">বাতিল</a>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- ===== বিপজ্জনক এলাকা ===== --}}
                <div class="panel danger-zone h-auto">
                    <div class="panel-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">খামার মুছুন</div>
                            <div class="small text-muted">মুছলে খামারটি আপনার তালিকা থেকে সরে যাবে এবং আর খোলা যাবে না।
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-danger" @click="open = true; typed = ''">
                            <i class="bi bi-trash3 me-1"></i>খামার মুছুন
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="panel h-auto">
                    <div class="panel-head">খামারের অবস্থা</div>
                    <div class="panel-body">
                        @isset($subscription)
                            @php $pillColors = ['info' => 'blue', 'success' => 'green', 'warning' => 'orange', 'danger' => 'red']; @endphp
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold">{{ $subscription->plan->name }}</span>
                                <span
                                    class="pill {{ $pillColors[$subscription->badgeClass()] ?? 'gray' }}">{{ $subscription->statusLabel() }}</span>
                            </div>
                            @php $end = $subscription->endDate(); @endphp
                            @if ($end)
                                <div class="small text-muted mt-1">মেয়াদ {{ $end->format('d/m/Y') }} পর্যন্ত</div>
                            @endif
                            @can('manageBilling', $farm)
                                <a href="{{ route('farms.billing.index', $farm) }}" class="btn btn-sm btn-outline-primary mt-3">
                                    <i class="bi bi-credit-card me-1"></i>সাবস্ক্রিপশন দেখুন
                                </a>
                            @endcan
                        @endisset
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== খামার মোছার নিশ্চিতকরণ ===== --}}
        <div class="sheet-backdrop" x-show="open" x-transition.opacity style="display: none" @click.self="open = false">
            <div class="sheet" role="dialog" aria-modal="true">
                <form method="POST" action="{{ route('farms.destroy', $farm) }}">
                    @csrf
                    @method('DELETE')

                    <div class="sheet-head">
                        <span class="ic rose"><i class="bi bi-trash3"></i></span>
                        <div>
                            <h3>খামারটি মুছবেন?</h3>
                            <p>{{ $farm->name }}</p>
                        </div>
                    </div>

                    <p class="sheet-note">
                        মুছলে সব সদস্য এই খামারে ঢুকতে পারবেন না। ফিরিয়ে আনতে অ্যাডমিনের সাহায্য লাগবে।
                    </p>

                    <label for="confirm-name" class="form-label">
                        নিশ্চিত করতে খামারের নাম লিখুন: <strong>{{ $farm->name }}</strong>
                    </label>
                    <input id="confirm-name" type="text" class="form-control" x-model="typed" autocomplete="off">

                    <div class="sheet-actions">
                        <button type="button" class="btn btn-outline-secondary" @click="open = false">ফিরে যান</button>
                        <button type="submit" class="btn btn-danger" :disabled="typed !== name">হ্যাঁ, মুছুন</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
