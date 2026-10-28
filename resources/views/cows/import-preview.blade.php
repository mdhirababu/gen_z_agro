@extends('layouts.farm')
@section('title', 'ইমপোর্টের প্রিভিউ')

@section('content')
    @php
        $c = $analysis['counts'];
        $cats = config('farm.categories');
        $sexes = config('farm.sexes');
        $states = ['ok' => ['যোগ হবে', 'green'], 'exists' => ['আগেই আছে', 'gray'], 'error' => ['ভুল', 'red']];
        $canImport = $c['ok'] > 0 && !$analysis['over_capacity'];
    @endphp

    <div class="page-head">
        <div>
            <h1>ইমপোর্টের প্রিভিউ</h1>
            <p>এখনো কিছু যোগ হয়নি। নিচে দেখে নিশ্চিত করুন।</p>
        </div>
        <a href="{{ route('farms.cows.import', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>অন্য ফাইল দিন
        </a>
    </div>

    @if ($errors->has('limit'))
        <div class="alert alert-danger">{{ $errors->first('limit') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi">
                <div class="kpi-label">মোট সারি</div>
                <div class="kpi-value">{{ $c['total'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi sky">
                <div class="kpi-label">যোগ হবে</div>
                <div class="kpi-value text-success">{{ $c['ok'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi lilac">
                <div class="kpi-label">আগেই আছে</div>
                <div class="kpi-value">{{ $c['exists'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi {{ $c['error'] > 0 ? 'rose' : '' }}">
                <div class="kpi-label">ভুল</div>
                <div class="kpi-value {{ $c['error'] > 0 ? 'text-danger' : '' }}">{{ $c['error'] }}</div>
            </div>
        </div>
    </div>

    @if ($analysis['over_capacity'])
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-octagon me-1"></i>
            আপনার প্ল্যানে আর <strong>{{ $analysis['slots'] }}টি</strong> সক্রিয় গরুর জায়গা আছে, কিন্তু আপনি
            <strong>{{ $analysis['active_new'] }}টি</strong> যোগ করতে চান। কম গরু দিন, অথবা প্ল্যান আপগ্রেড করুন।
        </div>
    @endif

    @if ($c['error'] > 0)
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-1"></i>
            ভুল সারিগুলো বাদ যাবে, সঠিকগুলো যোগ হবে। ভুলগুলো ঠিক করে ফাইলটা আবার দিলে আগের গরুগুলো "আগেই আছে" হয়ে বাদ যাবে,
            ডুপ্লিকেট হবে না।
        </div>
    @endif

    @if ($analysis['existing_zero'] > 0)
        <div class="alert alert-info">
            <i class="bi bi-lightbulb me-1"></i>
            {{ $analysis['existing_zero'] }}টি "আগে থেকে ছিল" গরুর প্রারম্ভিক মূল্য নেই (০ ধরা হবে)। এদের বিক্রি করলে
            লাভ-ক্ষতির হিসাবে পুরো বিক্রির টাকাই লাভ দেখাবে। চাইলে ফাইলে মূল্য দিয়ে আবার আপলোড করুন, বা পরে গরুর পাতা থেকে
            বদলান।
        </div>
    @endif

    <div class="table-card mb-3">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>সারি</th>
                        <th>ট্যাগ</th>
                        <th>ধরন ও লিঙ্গ</th>
                        <th>অবস্থা</th>
                        <th>বার্তা</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($analysis['rows'] as $row)
                        @php [$label, $pill] = $states[$row['state']]; @endphp
                        <tr>
                            <td class="text-nowrap">{{ $row['row'] }}</td>
                            <td>
                                <span class="fw-semibold">{{ $row['tag'] ?? '—' }}</span>
                                @if ($row['name'])
                                    <div class="small text-muted">{{ $row['name'] }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $row['category'] ? $cats[$row['category']]['label'] ?? $row['category'] : '—' }},
                                {{ $row['sex'] ? $sexes[$row['sex']] ?? $row['sex'] : '—' }}
                            </td>
                            <td><span class="pill {{ $pill }}">{{ $label }}</span></td>
                            <td>
                                @foreach ($row['errors'] as $message)
                                    <div class="small text-danger">{{ $message }}</div>
                                @endforeach
                                @foreach ($row['warnings'] as $message)
                                    <div class="small text-warning-emphasis">{{ $message }}</div>
                                @endforeach
                                @if ($row['state'] === 'exists')
                                    <div class="small text-muted">এই ট্যাগের গরু খামারে আগেই আছে।</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <form method="POST" action="{{ route('farms.cows.import.store', [$farm, $token]) }}" class="no-print">
        @csrf
        <button class="btn btn-primary" @disabled(!$canImport)>
            <i class="bi bi-check-lg me-1"></i>{{ $c['ok'] }}টি গরু যোগ করুন
        </button>
        <a href="{{ route('farms.cows.import', $farm) }}" class="btn btn-outline-secondary">বাতিল</a>
    </form>
@endsection
