@extends('layouts.farm')
@section('title', 'প্ল্যান সম্পাদনা')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $plan->name }}</h1>
            <p>দাম, মেয়াদ, সীমা ও সুবিধা ঠিক করুন।</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="pill gray">{{ $plan->slug }}</span>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>প্ল্যানের তালিকা
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.plans.update', $plan) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-lg-8">
                {{-- ===== মূল তথ্য ===== --}}
                <div class="panel h-auto mb-3">
                    <div class="panel-head">মূল তথ্য</div>
                    <div class="panel-body">
                        <div class="field">
                            <label for="name" class="form-label">প্ল্যানের নাম</label>
                            <input id="name" type="text" name="name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $plan->name) }}" required>
                            @error('name')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="field mb-md-0">
                                    <label for="price" class="form-label">দাম</label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input id="price" type="number" step="0.01" min="0" name="price"
                                            class="form-control @error('price') is-invalid @enderror"
                                            value="{{ old('price', $plan->price) }}" required>
                                    </div>
                                    @error('price')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field mb-0">
                                    <label for="duration_days" class="form-label">মেয়াদ</label>
                                    <div class="input-group">
                                        <input id="duration_days" type="number" min="1" name="duration_days"
                                            class="form-control @error('duration_days') is-invalid @enderror"
                                            value="{{ old('duration_days', $plan->duration_days) }}" required>
                                        <span class="input-group-text">দিন</span>
                                    </div>
                                    @error('duration_days')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== সীমা ===== --}}
                <div class="panel h-auto mb-3">
                    <div class="panel-head">সীমা</div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="field mb-md-0">
                                    <label for="max_cows" class="form-label">সর্বোচ্চ গরু</label>
                                    <input id="max_cows" type="number" min="1" name="max_cows"
                                        class="form-control @error('max_cows') is-invalid @enderror" placeholder="সীমাহীন"
                                        value="{{ old('max_cows', $plan->max_cows) }}">
                                    <div class="field-hint">ফাঁকা রাখলে সীমাহীন।</div>
                                    @error('max_cows')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field mb-0">
                                    <label for="max_members" class="form-label">সর্বোচ্চ সদস্য</label>
                                    <input id="max_members" type="number" min="1" name="max_members"
                                        class="form-control @error('max_members') is-invalid @enderror"
                                        placeholder="সীমাহীন" value="{{ old('max_members', $plan->max_members) }}">
                                    <div class="field-hint">মালিকসহ গণনা হয়। ফাঁকা রাখলে সীমাহীন।</div>
                                    @error('max_members')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== সুবিধা ===== --}}
                <div class="panel h-auto mb-3">
                    <div class="panel-head">সুবিধা</div>
                    <div class="panel-body pt-2 pb-2">
                        <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                            <div>
                                <label for="f1" class="fw-medium mb-0">Excel/PDF Export</label>
                                <div class="small text-muted">রিপোর্ট ডাউনলোড করার সুবিধা</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="feature_export"
                                    id="f1" value="1" @checked(old('feature_export', $plan->allows('export')))>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                            <div>
                                <label for="f2" class="fw-medium mb-0">গরুভিত্তিক লাভ-ক্ষতি রিপোর্ট</label>
                                <div class="small text-muted">প্রতিটি গরুর বিনিয়োগ ও আয়ের হিসাব</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="feature_cow_profit_report" id="f2" value="1"
                                    @checked(old('feature_cow_profit_report', $plan->allows('cow_profit_report')))>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center py-3">
                            <div>
                                <label for="f3" class="fw-medium mb-0">নতুন গ্রাহকদের জন্য চালু</label>
                                <div class="small text-muted">বন্ধ করলে পেমেন্ট ফর্মে প্ল্যানটি আর দেখাবে না</div>
                            </div>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active"
                                    id="f3" value="1" @checked(old('is_active', $plan->is_active))>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg me-1"></i>সংরক্ষণ করুন
                    </button>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-outline-secondary">বাতিল</a>
                </div>
            </div>

            {{-- ===== পরিবর্তনের প্রভাব ===== --}}
            <div class="col-lg-4">
                <div class="panel h-auto">
                    <div class="panel-head">পরিবর্তনের প্রভাব</div>
                    <div class="panel-body">
                        <ul class="todo-list">
                            <li>
                                <i class="bi bi-currency-exchange"></i>
                                <span>দাম ও মেয়াদ বদলালে শুধু <strong>পরের পেমেন্টে</strong> কার্যকর হবে। চলমান
                                    সাবস্ক্রিপশনের মেয়াদ বদলাবে না।</span>
                            </li>
                            <li>
                                <i class="bi bi-people"></i>
                                <span>সীমা কমালে আগে থেকে থাকা সদস্য বা গরু মুছে যায় না, শুধু নতুন যোগ করা আটকায়।</span>
                            </li>
                            <li>
                                <i class="bi bi-eye-slash"></i>
                                <span>"চালু" বন্ধ করলে যারা এই প্ল্যানে আছে তাদের কিছু হবে না, শুধু নতুন কেউ এটা বেছে নিতে
                                    পারবে না।</span>
                            </li>
                            <li>
                                <i class="bi bi-gift"></i>
                                <span>ট্রায়াল কোন প্ল্যানের সুবিধায় শুরু হবে, সেটা <code>config/subscription.php</code>-এর
                                    <code>trial_plan</code> থেকে ঠিক হয়।</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
