@extends('layouts.farm')
@section('title', 'নতুন গরু')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $mother ? 'বাছুরের জন্ম নথিভুক্ত করুন' : 'নতুন গরু বা বাছুর যোগ করুন' }}</h1>
            <p>
                @if ($mother)
                    মা: <strong>{{ $mother->tag_number }}</strong>
                    @if ($mother->name)
                        ({{ $mother->name }})
                    @endif
                @else
                    ট্যাগ নম্বর, ধরন আর কোথা থেকে এসেছে, এই তথ্যগুলো দিন।
                @endif
            </p>
        </div>
        <a href="{{ route('farms.cows.index', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>তালিকা
        </a>
    </div>

    @if ($errors->has('limit'))
        <div class="alert alert-danger">{{ $errors->first('limit') }}</div>
    @endif

    @if ($usage['full'])
        <div class="alert alert-warning">
            আপনার প্ল্যানের গরুর সীমা শেষ ({{ $usage['active'] }} / {{ $usage['max'] }})। নতুন গরু যোগ করতে প্ল্যান আপগ্রেড
            করুন।
            @can('manageBilling', $farm)
                <div class="mt-2">
                    <a class="btn btn-sm btn-primary" href="{{ route('farms.billing.index', $farm) }}">
                        <i class="bi bi-credit-card me-1"></i>সাবস্ক্রিপশন দেখুন
                    </a>
                </div>
            @endcan
        </div>
    @else
        <div class="row g-3">
            <div class="col-lg-8">
                <form method="POST" action="{{ route('farms.cows.store', $farm) }}" enctype="multipart/form-data">
                    @csrf
                    @include('cows._form', [
                        'submitLabel' => 'সংরক্ষণ করুন',
                        'cancelUrl' => route('farms.cows.index', $farm),
                    ])
                </form>
            </div>

            <div class="col-lg-4">
                <div class="panel h-auto mb-3">
                    <div class="panel-head">প্ল্যানের গরুর সীমা</div>
                    <div class="panel-body">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">সক্রিয় গরু</span>
                            <span class="fw-semibold">{{ $usage['active'] }} / {{ $usage['max'] ?? 'সীমাহীন' }}</span>
                        </div>
                        @if ($usage['max'])
                            <div class="usage-bar"><span
                                    style="width: {{ min(100, round(($usage['active'] / $usage['max']) * 100)) }}%"></span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="panel h-auto">
                    <div class="panel-head">কয়েকটা পরামর্শ</div>
                    <div class="panel-body">
                        <ul class="todo-list">
                            <li><i class="bi bi-tag"></i><span>খামারে প্রতিটি গরুর ট্যাগ নম্বর আলাদা হতে হবে। কানের ট্যাগের
                                    নম্বরই দিন।</span></li>
                            <li><i class="bi bi-heart"></i><span>খামারে বাছুর জন্মালে "খামারে জন্ম" বেছে নিন। মা গরুর পুরোনো
                                    খরচ বাছুরের ঘাড়ে চাপবে না।</span></li>
                            <li><i class="bi bi-calendar-check"></i><span>সঠিক জন্মতারিখ না জানলে আনুমানিক তারিখ দিয়ে টিক
                                    চিহ্ন দিন।</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
