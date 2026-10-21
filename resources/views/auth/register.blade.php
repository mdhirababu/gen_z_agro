@extends('layouts.app')
@section('title', 'নতুন অ্যাকাউন্ট')

@section('content')
    <div class="auth-panel">
        <h2 class="auth-title">নতুন অ্যাকাউন্ট খুলুন</h2>
        <p class="auth-sub">অ্যাকাউন্ট খুলে খামার তৈরি করলে {{ config('subscription.trial_days') }} দিনের ফ্রি ট্রায়াল শুরু
            হবে।</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div class="field">
                <label for="name" class="form-label">আপনার নাম</label>
                <div class="field-control">
                    <i class="bi bi-person lead-ic"></i>
                    <input id="name" type="text" name="name" value="{{ old('name') }}"
                        class="form-control @error('name') is-invalid @enderror" required autocomplete="name" autofocus>
                </div>
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="email" class="form-label">ইমেইল</label>
                <div class="field-control">
                    <i class="bi bi-envelope lead-ic"></i>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror" placeholder="name@example.com" required
                        autocomplete="email">
                </div>
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- একটা বাটনে দুই পাসওয়ার্ড ঘরই দেখা বা লুকানো যায় --}}
            <div x-data="{ show: false }">
                <div class="field">
                    <label for="password" class="form-label">পাসওয়ার্ড</label>
                    <div class="field-control has-toggle">
                        <i class="bi bi-lock lead-ic"></i>
                        <input id="password" name="password" type="password" :type="show ? 'text' : 'password'"
                            class="form-control @error('password') is-invalid @enderror" required
                            autocomplete="new-password">
                        <button type="button" class="toggle" @click="show = !show"
                            :aria-label="show ? 'পাসওয়ার্ড লুকান' : 'পাসওয়ার্ড দেখান'">
                            <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @else
                        <div class="field-hint">কমপক্ষে ৮ অক্ষর দিন।</div>
                    @enderror
                </div>

                <div class="field">
                    <label for="password-confirm" class="form-label">পাসওয়ার্ড আবার লিখুন</label>
                    <div class="field-control">
                        <i class="bi bi-shield-lock lead-ic"></i>
                        <input id="password-confirm" name="password_confirmation" type="password"
                            :type="show ? 'text' : 'password'" class="form-control" required autocomplete="new-password">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary auth-btn mt-2">অ্যাকাউন্ট খুলুন</button>
        </form>

        @if (Route::has('login'))
            <div class="auth-alt">
                আগে থেকেই অ্যাকাউন্ট আছে? <a href="{{ route('login') }}">লগইন করুন</a>
            </div>
        @endif
    </div>
@endsection
