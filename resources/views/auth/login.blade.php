@extends('layouts.app')
@section('title', 'লগইন')

@section('content')
    <div class="auth-panel">
        <h2 class="auth-title">লগইন করুন</h2>
        <p class="auth-sub">আপনার খামারের হিসাবে ফিরে যান।</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email" class="form-label">ইমেইল</label>
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

            <div class="field" x-data="{ show: false }">
                <label for="password" class="form-label">পাসওয়ার্ড</label>
                <div class="field-control has-toggle">
                    <i class="bi bi-lock lead-ic"></i>
                    <input id="password" name="password" type="password" :type="show ? 'text' : 'password'"
                        class="form-control @error('password') is-invalid @enderror" required
                        autocomplete="current-password">
                    <button type="button" class="toggle" @click="show = !show"
                        :aria-label="show ? 'পাসওয়ার্ড লুকান' : 'পাসওয়ার্ড দেখান'">
                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                    </button>
                </div>
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="auth-row">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember"
                        {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label" for="remember">আমাকে মনে রাখুন</label>
                </div>

                @if (Route::has('password.request'))
                    <a class="small fw-medium" href="{{ route('password.request') }}">পাসওয়ার্ড ভুলে গেছেন?</a>
                @endif
            </div>

            <button type="submit" class="btn btn-primary auth-btn">লগইন</button>
        </form>

        @if (Route::has('register'))
            <div class="auth-alt">
                এখনো অ্যাকাউন্ট নেই? <a href="{{ route('register') }}">নতুন অ্যাকাউন্ট খুলুন</a>
            </div>
        @endif
        <a href="{{ route('privacy') }}">গোপনীয়তা নীতি</a> · <a href="{{ route('terms') }}">শর্তাবলী</a>
    </div>
@endsection
