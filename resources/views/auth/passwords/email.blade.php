@extends('layouts.app')
@section('title', 'পাসওয়ার্ড রিসেট')

@section('content')
    <div class="auth-panel">
        <h2 class="auth-title">পাসওয়ার্ড রিসেট করুন</h2>
        <p class="auth-sub">আপনার ইমেইল অ্যাড্রেসটি দিন, আমরা আপনাকে একটি পাসওয়ার্ড রিসেট লিংক পাঠিয়ে দেব।</p>

        @if (session('status'))
            <div class="alert alert-success mb-4" role="alert" style="font-size: 0.9rem; border-radius: 0.75rem;">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            {{-- ইমেইল ফিল্ড --}}
            <div class="field">
                <label for="email" class="form-label">ইমেইল অ্যাড্রেস</label>
                <div class="field-control">
                    <i class="bi bi-envelope lead-ic"></i>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror" placeholder="name@example.com" required
                        autocomplete="email" autofocus>
                </div>
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary auth-btn mt-2">
                রিসেট লিংক পাঠান
            </button>
        </form>

        {{-- লগইন পেজে ফেরার লিংক --}}
        <div class="auth-alt"
            style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #e5e7eb; text-align: center; font-size: 0.95rem; color: #6b7280;">
            মনে পড়ে গেছে? <a href="{{ route('login') }}"
                style="color: #1a7f4b; font-weight: 600; text-decoration: none;">লগইনে ফিরে যান</a>
        </div>
    </div>
@endsection
