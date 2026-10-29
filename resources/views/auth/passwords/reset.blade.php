@extends('layouts.app')
@section('title', 'নতুন পাসওয়ার্ড সেট করুন')

@section('content')
    <div class="auth-panel">
        <h2 class="auth-title">নতুন পাসওয়ার্ড দিন</h2>
        <p class="auth-sub">আপনার অ্যাকাউন্টের জন্য একটি শক্তিশালী নতুন পাসওয়ার্ড তৈরি করুন।</p>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            {{-- হিডেন টোকেন --}}
            <input type="hidden" name="token" value="{{ $token }}">

            {{-- ইমেইল ফিল্ড --}}
            <div class="field">
                <label for="email" class="form-label">ইমেইল অ্যাড্রেস</label>
                <div class="field-control">
                    <i class="bi bi-envelope lead-ic"></i>
                    <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}"
                        class="form-control @error('email') is-invalid @enderror" placeholder="name@example.com" required
                        autocomplete="email" autofocus>
                </div>
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- নতুন পাসওয়ার্ড --}}
            <div class="field">
                <label for="password" class="form-label">নতুন পাসওয়ার্ড</label>
                <div class="field-control has-toggle">
                    <i class="bi bi-lock lead-ic"></i>
                    <input id="password" name="password" type="password"
                        class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                    <button type="button" class="toggle" onclick="togglePassword('password', 'eyeIcon1')"
                        aria-label="পাসওয়ার্ড দেখান">
                        <i class="bi bi-eye" id="eyeIcon1"></i>
                    </button>
                </div>
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- পাসওয়ার্ড কনফার্ম --}}
            <div class="field">
                <label for="password-confirm" class="form-label">পাসওয়ার্ড নিশ্চিত করুন</label>
                <div class="field-control has-toggle">
                    <i class="bi bi-shield-check lead-ic"></i>
                    <input id="password-confirm" name="password_confirmation" type="password" class="form-control" required
                        autocomplete="new-password">
                    <button type="button" class="toggle" onclick="togglePassword('password-confirm', 'eyeIcon2')"
                        aria-label="পাসওয়ার্ড দেখান">
                        <i class="bi bi-eye" id="eyeIcon2"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary auth-btn mt-2">
                পাসওয়ার্ড আপডেট করুন
            </button>
        </form>

        {{-- লগইন পেজে ফেরার লিংক --}}
        <div class="auth-alt"
            style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #e5e7eb; text-align: center; font-size: 0.95rem; color: #6b7280;">
            মনে পড়ে গেছে? <a href="{{ route('login') }}"
                style="color: #1a7f4b; font-weight: 600; text-decoration: none;">লগইনে ফিরে যান</a>
        </div>
    </div>

    {{-- পাসওয়ার্ড শো/হাইড করার জন্য জাভাস্ক্রিপ্ট --}}
    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
    </script>
@endsection
