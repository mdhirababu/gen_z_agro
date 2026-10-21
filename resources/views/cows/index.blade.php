@extends('layouts.farm')
@section('title', 'গরু ও বাছুর')

@section('content')
    <div class="page-head">
        <div>
            <h1>গরু ও বাছুর</h1>
            <p>খামারের সব গরু, বাছুর আর ষাঁড়ের তালিকা।</p>
        </div>
        @can('create', [App\Models\Cow::class, $farm])
            <a href="{{ route('farms.cows.create', $farm) }}" class="btn btn-primary d-none d-lg-inline-flex align-items-center">
                <i class="bi bi-plus-lg me-1"></i>নতুন গরু
            </a>
        @endcan
    </div>

    <livewire:cow-list :farm-id="$farm->id" />
@endsection
