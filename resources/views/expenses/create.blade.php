@extends('layouts.farm')
@section('title', 'নতুন খরচ')

@section('content')
    <div class="page-head">
        <div>
            <h1>নতুন খরচ যোগ করুন</h1>
            <p>কী কিনলেন বা কাকে কত দিলেন, লিখে রাখুন।</p>
        </div>
        <a href="{{ route('farms.expenses.index', $farm) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>তালিকা
        </a>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <form method="POST" action="{{ route('farms.expenses.store', $farm) }}">
                @csrf
                @include('expenses._form', [
                    'submitLabel' => 'খরচ সংরক্ষণ করুন',
                    'cancelUrl' => route('farms.expenses.index', $farm),
                ])
            </form>
        </div>
    </div>
@endsection
