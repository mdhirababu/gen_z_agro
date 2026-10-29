@extends('layouts.app')
@section('title', 'লগইন')

@section('content')
    <div class="auth-panel">
        <h2 class="auth-title">লগইন করুন</h2>
        <p class="auth-sub">আপনার খামারের হিসাবে ফিরে যান।</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            {{-- ইমেইল --}}
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

            {{-- পাসওয়ার্ড --}}
            <div class="field">
                <label for="password" class="form-label">পাসওয়ার্ড</label>
                <div class="field-control has-toggle">
                    <i class="bi bi-lock lead-ic"></i>

                    {{-- এখানে placeholder যোগ করা হয়েছে --}}
                    <input id="password" name="password" type="password"
                        class="form-control @error('password') is-invalid @enderror" placeholder="••••••••" required
                        autocomplete="current-password">

                    {{-- একটি মাত্র বাটন --}}
                    <button type="button" class="toggle" id="toggleBtn" onclick="togglePassword()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- Remember + Forgot password --}}
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

        <div
            style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; text-align: center; font-size: 0.85rem; color: #6b7280; display: flex; align-items: center; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
            <a href="{{ route('privacy') }}" style="color: #6b7280; text-decoration: none; font-weight: 500;">গোপনীয়তা
                নীতি</a>
            <span style="color: #d1d5db;">·</span>
            <a href="{{ route('terms') }}" style="color: #6b7280; text-decoration: none; font-weight: 500;">শর্তাবলী</a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');

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
