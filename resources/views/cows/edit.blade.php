@extends('layouts.farm')
@section('title', 'গরু সম্পাদনা')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $cow->tag_number }} সম্পাদনা</h1>
            <p>তথ্য বদলে সংরক্ষণ করুন।</p>
        </div>
        <a href="{{ route('farms.cows.show', [$farm, $cow]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>বিবরণে ফিরুন
        </a>
    </div>

    @if ($errors->has('limit'))
        <div class="alert alert-danger">{{ $errors->first('limit') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <form method="POST" action="{{ route('farms.cows.update', [$farm, $cow]) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('cows._form', [
                    'submitLabel' => 'আপডেট করুন',
                    'cancelUrl' => route('farms.cows.show', [$farm, $cow]),
                ])
            </form>
        </div>
    </div>
@endsection
