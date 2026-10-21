@extends('layouts.farm')
@section('title', 'সদস্য')

@section('content')
    @php
        $roleMap = [
            'owner' => ['label' => 'মালিক', 'pill' => 'green', 'avatar' => 'mint'],
            'manager' => ['label' => 'ম্যানেজার', 'pill' => 'blue', 'avatar' => ''],
            'staff' => ['label' => 'স্টাফ', 'pill' => 'gray', 'avatar' => 'sand'],
        ];

        // মালিক সবসময় ওপরে
        $sorted = $members->sortBy(fn($m) => $m->id === $farm->owner_user_id ? 0 : 1)->values();
        $total = $sorted->count();
        $max = isset($subscription) ? $subscription->plan->max_members : null;
        $atLimit = $max !== null && $total >= $max;
        $percent = $max ? min(100, round(($total / $max) * 100)) : 0;
    @endphp

    <div x-data="{
        open: false,
        mode: 'role',
        p: {},
        act(mode, el) { this.mode = mode;
            this.p = { ...el.dataset };
            this.open = true; }
    }" @keydown.escape.window="open = false">

        <div class="page-head">
            <div>
                <h1>সদস্য</h1>
                <p>খামারে কারা কাজ করবেন এবং তাঁদের ভূমিকা কী, এখানে ঠিক করুন।</p>
            </div>
            <span class="pill gray">মোট {{ $total }} জন</span>
        </div>

        <div class="row g-3">

            {{-- ===== নতুন সদস্য যোগ ===== --}}
            <div class="col-lg-4">
                <div class="panel h-auto">
                    <div class="panel-head">নতুন সদস্য যোগ করুন</div>
                    <div class="panel-body">

                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">প্ল্যানের সদস্য সীমা</span>
                                <span class="fw-semibold">{{ $total }} / {{ $max ?? 'সীমাহীন' }}</span>
                            </div>
                            @if ($max !== null)
                                <div class="usage-bar {{ $atLimit ? 'is-full' : '' }}">
                                    <span style="width: {{ $percent }}%"></span>
                                </div>
                            @endif
                        </div>

                        @if ($atLimit)
                            <div class="alert alert-warning mb-0">
                                আপনার প্ল্যানের সদস্য সীমা শেষ। আরও সদস্য যোগ করতে প্ল্যান আপগ্রেড করুন।
                                <div class="mt-2">
                                    <a class="btn btn-sm btn-primary" href="{{ route('farms.billing.index', $farm) }}">
                                        <i class="bi bi-credit-card me-1"></i>সাবস্ক্রিপশন দেখুন
                                    </a>
                                </div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('farms.members.store', $farm) }}">
                                @csrf

                                <div class="field">
                                    <label for="email" class="form-label">সদস্যের ইমেইল</label>
                                    <div class="field-control">
                                        <i class="bi bi-envelope lead-ic"></i>
                                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                                            class="form-control @error('email') is-invalid @enderror"
                                            placeholder="name@example.com" required>
                                    </div>
                                    @error('email')
                                        <div class="field-error">{{ $message }}</div>
                                    @else
                                        <div class="field-hint">তিনি আগে এই সিস্টেমে রেজিস্টার করে থাকতে হবে।</div>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label for="role" class="form-label">ভূমিকা</label>
                                    <select id="role" name="role" class="form-select">
                                        <option value="manager" @selected(old('role') === 'manager')>ম্যানেজার</option>
                                        <option value="staff" @selected(old('role', 'staff') === 'staff')>স্টাফ</option>
                                    </select>
                                    <div class="field-hint">ম্যানেজার দৈনন্দিন কাজ পরিচালনা করবেন, স্টাফ তথ্য যোগ ও দেখতে
                                        পারবেন।</div>
                                </div>

                                <button class="btn btn-primary w-100">
                                    <i class="bi bi-person-plus me-1"></i>সদস্য যোগ করুন
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===== সদস্যদের তালিকা ===== --}}
            <div class="col-lg-8">
                <div class="panel member-list h-auto">
                    <div class="panel-head pb-3 border-bottom d-flex justify-content-between align-items-center">
                        <span>খামারের সদস্য</span>
                        <span class="small fw-normal text-muted">{{ $total }} জন</span>
                    </div>

                    @foreach ($sorted as $member)
                        @php
                            $role = $member->pivot->role;
                            $r = $roleMap[$role] ?? ['label' => $role, 'pill' => 'gray', 'avatar' => ''];
                            $isOwner = $member->id === $farm->owner_user_id;
                            $joined = $member->pivot->created_at;
                        @endphp

                        <div class="member-row" data-name="{{ $member->name }}" data-email="{{ $member->email }}"
                            data-role="{{ $role }}"
                            data-remove-url="{{ route('farms.members.destroy', [$farm, $member]) }}">

                            <span class="avatar lg {{ $r['avatar'] }}">{{ mb_substr($member->name, 0, 1) }}</span>

                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold text-truncate">
                                    {{ $member->name }}
                                    @if ($member->id === auth()->id())
                                        <span class="small fw-normal text-muted">(আপনি)</span>
                                    @endif
                                </div>
                                <div class="small text-muted text-truncate">{{ $member->email }}</div>
                            </div>

                            <span class="member-meta d-none d-md-block">
                                {{ $joined ? \Illuminate\Support\Carbon::parse($joined)->format('d/m/Y') : '—' }} থেকে
                            </span>

                            <span class="pill {{ $r['pill'] }}">{{ $r['label'] }}</span>

                            <div class="d-flex gap-1">
                                @if ($isOwner)
                                    <span class="icon-btn sm is-locked"
                                        title="মালিকের ভূমিকা বদলানো বা তাঁকে সরানো যায় না">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                @else
                                    <button type="button" class="icon-btn sm" title="ভূমিকা বদলান"
                                        aria-label="ভূমিকা বদলান" @click="act('role', $el.closest('.member-row'))">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="icon-btn sm danger" title="সদস্যকে সরান"
                                        aria-label="সদস্যকে সরান" @click="act('remove', $el.closest('.member-row'))">
                                        <i class="bi bi-person-dash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ===== পপ-আপ: ভূমিকা বদল / সদস্য সরানো ===== --}}
        <div class="sheet-backdrop" x-show="open" x-transition.opacity style="display: none" @click.self="open = false">
            <div class="sheet" role="dialog" aria-modal="true">

                {{-- ভূমিকা বদল --}}
                <form method="POST" action="{{ route('farms.members.store', $farm) }}" x-show="mode === 'role'">
                    @csrf
                    <input type="hidden" name="email" :value="p.email">

                    <div class="sheet-head">
                        <span class="ic sky"><i class="bi bi-pencil"></i></span>
                        <div>
                            <h3>ভূমিকা বদলান</h3>
                            <p x-text="p.name"></p>
                        </div>
                    </div>

                    <label for="sheet-role" class="form-label">নতুন ভূমিকা</label>
                    <select id="sheet-role" name="role" class="form-select" x-model="p.role">
                        <option value="manager">ম্যানেজার</option>
                        <option value="staff">স্টাফ</option>
                    </select>

                    <div class="sheet-actions">
                        <button type="button" class="btn btn-outline-secondary" @click="open = false">ফিরে যান</button>
                        <button type="submit" class="btn btn-primary">সংরক্ষণ করুন</button>
                    </div>
                </form>

                {{-- সদস্য সরানো --}}
                <form method="POST" :action="p.removeUrl" x-show="mode === 'remove'">
                    @csrf
                    @method('DELETE')

                    <div class="sheet-head">
                        <span class="ic rose"><i class="bi bi-person-dash"></i></span>
                        <div>
                            <h3>সদস্যকে সরাবেন?</h3>
                            <p x-text="p.name"></p>
                        </div>
                    </div>

                    <p class="sheet-note mb-0">
                        সরালে ইনি আর এই খামারের তথ্য দেখতে বা বদলাতে পারবেন না। তাঁর অ্যাকাউন্ট মুছে যাবে না, চাইলে পরে আবার
                        যোগ করা যাবে।
                    </p>

                    <div class="sheet-actions">
                        <button type="button" class="btn btn-outline-secondary" @click="open = false">ফিরে যান</button>
                        <button type="submit" class="btn btn-danger">হ্যাঁ, সরান</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
