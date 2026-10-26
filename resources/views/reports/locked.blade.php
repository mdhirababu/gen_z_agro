@extends('layouts.farm')
@section('title', 'গরুভিত্তিক লাভ-ক্ষতি')

@section('content')
    <div class="page-head">
        <div>
            <h1>গরুভিত্তিক লাভ-ক্ষতি</h1>
        </div>
        <a href="{{ route('farms.reports.index', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>সব রিপোর্ট
        </a>
    </div>

    <div class="panel h-auto">
        <div class="panel-body empty-state">
            <i class="bi bi-lock"></i>
            <h2>এই রিপোর্ট আপনার প্ল্যানে নেই</h2>
            <p>গরুভিত্তিক লাভ-ক্ষতি পেতে এমন প্ল্যান লাগবে যেখানে এই সুবিধা চালু আছে।</p>
            @can('manageBilling', $farm)
                <a href="{{ route('farms.billing.index', $farm) }}" class="btn btn-primary">প্ল্যান দেখুন</a>
            @endcan
        </div>
    </div>
@endsection
