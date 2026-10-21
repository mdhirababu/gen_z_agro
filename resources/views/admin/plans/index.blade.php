@extends('layouts.farm')
@section('title', 'প্ল্যান')

@section('content')
    <div class="page-head">
        <div>
            <h1>প্ল্যান ব্যবস্থাপনা</h1>
            <p>দাম, সীমা ও সুবিধা এখান থেকে ঠিক করুন।</p>
        </div>
        <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>অ্যাডমিন হোম
        </a>
    </div>

    <div class="row g-3">
        @foreach ($plans as $plan)
            <div class="col-md-6 col-xl-4">
                <div class="panel h-100">
                    <div class="panel-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="plan-name">{{ $plan->name }}</div>
                                <span class="tag gray">{{ $plan->slug }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="pill {{ $plan->is_active ? 'green' : 'gray' }}">{{ $plan->is_active ? 'চালু' : 'বন্ধ' }}</span>
                                <a href="{{ route('admin.plans.edit', $plan) }}" class="icon-btn sm" title="সম্পাদনা করুন"
                                    aria-label="সম্পাদনা করুন">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </div>

                        <div class="plan-price mt-3">৳ {{ number_format($plan->price, 0) }} <small>/
                                {{ $plan->duration_days }} দিন</small></div>

                        <ul class="plan-feats">
                            <li class="yes"><i
                                    class="bi bi-check-lg"></i>{{ $plan->max_cows ? $plan->max_cows . 'টি পর্যন্ত গরু' : 'সীমাহীন গরু' }}
                            </li>
                            <li class="yes"><i
                                    class="bi bi-check-lg"></i>{{ $plan->max_members ? $plan->max_members . ' জন পর্যন্ত সদস্য' : 'সীমাহীন সদস্য' }}
                            </li>
                            <li class="{{ $plan->allows('export') ? 'yes' : 'no' }}">
                                <i class="bi {{ $plan->allows('export') ? 'bi-check-lg' : 'bi-x-lg' }}"></i>Excel/PDF
                                Export
                            </li>
                            <li class="{{ $plan->allows('cow_profit_report') ? 'yes' : 'no' }}">
                                <i
                                    class="bi {{ $plan->allows('cow_profit_report') ? 'bi-check-lg' : 'bi-x-lg' }}"></i>গরুভিত্তিক
                                লাভ-ক্ষতি রিপোর্ট
                            </li>
                        </ul>

                        <div class="mt-3 pt-3 border-top small text-muted">
                            <i class="bi bi-buildings me-1"></i>{{ $plan->subscriptions_count }}টি খামার এই প্ল্যানে আছে
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
