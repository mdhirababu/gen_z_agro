@extends('layouts.farm')
@section('title', 'বিক্রি সম্পাদনা')

@section('content')
    <div class="page-head">
        <div>
            <h1>বিক্রি সম্পাদনা</h1>
            <p>{{ $sale->typeMeta()['label'] }}, {{ \App\Support\Money::fmt($sale->total_amount) }}</p>
        </div>
        <a href="{{ route('farms.sales.show', [$farm, $sale]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>বিস্তারিতে ফিরুন
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="alert alert-info">
                টাকার অঙ্ক ও গরু এখান থেকে বদলানো যায় না। অঙ্কে ভুল থাকলে বিক্রিটি বাতিল করে নতুন করে যোগ করুন।
            </div>

            <form method="POST" action="{{ route('farms.sales.update', [$farm, $sale]) }}">
                @csrf
                @method('PUT')

                <div class="panel h-auto mb-3">
                    <div class="panel-head">বদলানো যায় এমন তথ্য</div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="sold_at" class="form-label">তারিখ</label>
                                    <input id="sold_at" type="date" name="sold_at" max="{{ now()->toDateString() }}"
                                        value="{{ old('sold_at', $sale->sold_at->format('Y-m-d')) }}"
                                        class="form-control @error('sold_at') is-invalid @enderror" required>
                                    @error('sold_at')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="field">
                                    <label for="customer_id" class="form-label">ক্রেতা</label>
                                    <select id="customer_id" name="customer_id"
                                        class="form-select @error('customer_id') is-invalid @enderror">
                                        <option value="">নগদ বিক্রি, ক্রেতার নাম নেই</option>
                                        @foreach ($customers as $c)
                                            <option value="{{ $c->id }}" @selected((string) old('customer_id', $sale->customer_id) === (string) $c->id)>
                                                {{ $c->name }}@if ($c->phone)
                                                    ({{ $c->phone }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label for="description" class="form-label">বিবরণ</label>
                            <input id="description" type="text" name="description"
                                value="{{ old('description', $sale->description) }}"
                                class="form-control @error('description') is-invalid @enderror">
                            @error('description')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="field mb-0">
                            <label for="notes" class="form-label">নোট</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes', $sale->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>আপডেট
                        করুন</button>
                    <a href="{{ route('farms.sales.show', [$farm, $sale]) }}" class="btn btn-outline-secondary">বাতিল</a>
                </div>
            </form>
        </div>
    </div>
@endsection
