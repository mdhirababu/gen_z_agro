@extends('layouts.farm')
@section('title', 'গরুর তালিকা ইমপোর্ট')

@section('content')
    <div class="page-head">
        <div>
            <h1>গরুর তালিকা ইমপোর্ট</h1>
            <p>খাতা বা Excel-এ থাকা গরুর তালিকা একবারে তুলুন।</p>
        </div>
        <a href="{{ route('farms.cows.index', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>গরুর তালিকা
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel h-auto mb-3">
                <div class="panel-head">তিনটা ধাপ</div>
                <div class="panel-body">
                    <div class="d-flex gap-3 mb-3">
                        <span class="ic mint"><i class="bi bi-1-circle"></i></span>
                        <div>
                            <div class="fw-semibold">টেমপ্লেট নামান</div>
                            <div class="small text-muted mb-2">ড্রপডাউন তালিকা ও নির্দেশনাসহ Excel ফাইল।</div>
                            <a href="{{ route('farms.cows.import.template', $farm) }}"
                                class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-file-earmark-excel me-1"></i>টেমপ্লেট (.xlsx)
                            </a>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3">
                        <span class="ic sky"><i class="bi bi-2-circle"></i></span>
                        <div>
                            <div class="fw-semibold">গরুর তথ্য ভরুন</div>
                            <div class="small text-muted">প্রতি সারিতে একটা গরু। শুধু <strong>ট্যাগ নম্বর, ধরন ও
                                    লিঙ্গ</strong> জরুরি, বাকি সব ঐচ্ছিক। একবারে সর্বোচ্চ {{ $maxRows }}টি।</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <span class="ic peach"><i class="bi bi-3-circle"></i></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">ফাইল আপলোড করুন</div>
                            <div class="small text-muted mb-2">আপলোডের পর আগে প্রিভিউ দেখবেন, তারপর নিশ্চিত করলে গরু যোগ
                                হবে।</div>

                            <form method="POST" action="{{ route('farms.cows.import.upload', $farm) }}"
                                enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="file" accept=".xlsx,.csv"
                                    class="form-control @error('file') is-invalid @enderror" required>
                                @error('file')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                                <button class="btn btn-primary mt-2"><i class="bi bi-upload me-1"></i>প্রিভিউ দেখুন</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel h-auto mb-3">
                <div class="panel-head">আপনার প্ল্যান</div>
                <div class="panel-body">
                    @if ($slots === null)
                        গরুর সংখ্যার সীমা নেই।
                    @else
                        আর <strong>{{ $slots }}টি</strong> সক্রিয় গরু যোগ করা যাবে।
                    @endif
                    <div class="small text-muted mt-1">মৃত বা সরানো গরু জায়গা নেয় না।</div>
                </div>
            </div>

            <details class="panel h-auto">
                <summary class="panel-head" style="cursor: pointer">কোন কলামে কী লিখবেন</summary>
                <div class="panel-body">
                    @foreach ($allowed as $title => $values)
                        <div class="mb-2">
                            <div class="fw-semibold">{{ $title }}</div>
                            <div class="small text-muted">{{ implode(', ', $values) }}</div>
                        </div>
                    @endforeach
                    <div class="small text-muted">তারিখ: 2024-05-10 বা 10/05/2024। টাকা: 80000 (কমা ও বাংলা সংখ্যা চলে)।
                        মায়ের ট্যাগ: মা এই ফাইলে বা আগে থেকে থাকলে চলে।</div>
                </div>
            </details>
        </div>
    </div>
@endsection
