@extends('layouts.farm')
@section('title', 'খরচ সম্পাদনা')

@section('content')
    <div class="page-head">
        <div>
            <h1>খরচ সম্পাদনা</h1>
            <p>{{ $expense->categoryMeta()['label'] }}, {{ $expense->expense_date->format('d/m/Y') }}</p>
        </div>
        <a href="{{ route('farms.expenses.show', [$farm, $expense]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>বিস্তারিতে ফিরুন
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('farms.expenses.update', [$farm, $expense]) }}">
                @csrf
                @method('PUT')
                @include('expenses._form', [
                    'submitLabel' => 'আপডেট করুন',
                    'cancelUrl' => route('farms.expenses.show', [$farm, $expense]),
                ])
            </form>
        </div>
    </div>
@endsection
