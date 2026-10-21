@extends('layouts.farm')
@section('title', 'নতুন খামার')

@section('content')
    <div class="page-head">
        <div>
            <h1>নতুন খামার তৈরি করুন</h1>
            <p>খামারের মূল তথ্য দিন। এগুলো পরে সেটিংস থেকে বদলানো যাবে।</p>
        </div>
        <a href="{{ route('farms.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>খামারের তালিকা
        </a>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('farms.store') }}">
                @csrf
                <div class="panel h-auto">
                    <div class="panel-head">খামারের তথ্য</div>
                    <div class="panel-body">
                        @include('farms._form')

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-lg me-1"></i>খামার তৈরি করুন
                            </button>
                            <a href="{{ route('farms.index') }}" class="btn btn-outline-secondary">বাতিল</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="panel h-auto">
                <div class="panel-head">তৈরি করলে কী হবে</div>
                <div class="panel-body">
                    <ul class="todo-list">
                        <li>
                            <i class="bi bi-gift"></i>
                            <span><strong>{{ config('subscription.trial_days') }} দিনের ফ্রি ট্রায়াল</strong> শুরু হবে,
                                {{ ucfirst(config('subscription.trial_plan')) }} প্ল্যানের সুবিধায়।</span>
                        </li>
                        <li>
                            <i class="bi bi-person-badge"></i>
                            <span>আপনি এই খামারের <strong>মালিক</strong> হবেন এবং সব কিছু পরিচালনা করতে পারবেন।</span>
                        </li>
                        <li>
                            <i class="bi bi-people"></i>
                            <span>পরে ম্যানেজার ও স্টাফ যোগ করতে পারবেন।</span>
                        </li>
                        <li>
                            <i class="bi bi-shield-check"></i>
                            <span>খামারের তথ্য শুধু এই খামারের সদস্যরাই দেখতে পাবেন।</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
