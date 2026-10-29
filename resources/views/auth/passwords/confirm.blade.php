@extends('layouts.app')
@section('title', 'পাসওয়ার্ড নিশ্চিত করুন')

@section('content')
    <div class="auth-panel">
        <h2 class="auth-title">পাসওয়ার্ড নিশ্চিত করুন</h2>
        <p class="auth-sub">নিরাপত্তার স্বার্থে, কাজ চালিয়ে যাওয়ার আগে দয়া করে আপনার পাসওয়ার্ডটি পুনরায় দিন।</p>

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            {{-- পাসওয়ার্ড ফিল্ড --}}
            <div class="field">
                <label for="password" class="form-label">পাসওয়ার্ড</label>
                <div class="field-control has-toggle">
                    <i class="bi bi-lock lead-ic"></i>

                    <input id="password" name="password" type="password"
                        class="form-control @error('password') is-invalid @enderror" required
                        autocomplete="current-password">

                    {{-- পাসওয়ার্ড শো/হাইড আইকন --}}
                    <button type="button" class="toggle" onclick="togglePassword()" aria-label="পাসওয়ার্ড দেখান">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary auth-btn mt-2">
                নিশ্চিত করুন
            </button>
        </form>

        {{-- পাসওয়ার্ড ভুলে গেলে এবং ফিরে যাওয়ার লিংক --}}
        <div class="auth-row mt-4"
            style="display: flex; flex-direction: column; gap: 0.8rem; align-items: center; border-top: 1px solid #e5e7eb; padding-top: 1.25rem;">

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" style="color: #6b7280; font-size: 0.9rem; text-decoration: none;">
                    পাসওয়ার্ড ভুলে গেছেন?
                </a>
            @endif

            <a href="{{ route('login') }}"
                style="color: #1a7f4b; font-weight: 600; text-decoration: none; font-size: 0.95rem;">
                লগইনে ফিরে যান
            </a>

        </div>
    </div>

    {{-- পাসওয়ার্ড শো/হাইড করার জাভাস্ক্রিপ্ট --}}
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
