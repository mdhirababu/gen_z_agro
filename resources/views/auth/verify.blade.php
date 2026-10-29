@extends('layouts.app')
@section('title', 'ইমেইল ভেরিফাই করুন')

@section('content')
    <div class="auth-panel">
        <div class="text-center mb-4">
            <div class="ic mint mx-auto" style="width: 4rem; height: 4rem; font-size: 2rem; margin-bottom: 1rem;">
                <i class="bi bi-envelope-check"></i>
            </div>
            <h2 class="auth-title">ইমেইল ভেরিফাই করুন</h2>
            <p class="auth-sub">কাজ চালিয়ে যাওয়ার আগে, দয়া করে আপনার ইমেইলে পাঠানো ভেরিফিকেশন লিংকটি চেক করুন।</p>
        </div>

        @if (session('resent'))
            <div class="alert alert-success mb-4" role="alert"
                style="font-size: 0.9rem; border-radius: 0.75rem; background-color: #dff3e6; color: #1f7a45; border: none;">
                <i class="bi bi-check-circle-fill me-2"></i>
                আপনার ইমেইল অ্যাড্রেসে একটি নতুন ভেরিফিকেশন লিংক পাঠানো হয়েছে।
            </div>
        @endif

        <div
            style="background-color: #f7faf8; padding: 1.25rem; border-radius: 1rem; border: 1px solid #dbe5df; text-align: center; margin-bottom: 1.5rem;">
            <p style="margin: 0; color: #52665d; font-size: 0.95rem;">
                ইমেইলটি পাননি?
            </p>
            <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
                @csrf
                <button type="submit" class="btn btn-link p-0 m-0 align-baseline"
                    style="color: #1a7f4b; font-weight: 600; text-decoration: none; font-size: 0.95rem;">
                    আবার একটি লিংক পাঠান <i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>

        {{-- লগআউট বা লগইনে ফেরার লিংক --}}
        <div class="auth-alt"
            style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #e5e7eb; text-align: center; font-size: 0.95rem; color: #6b7280;">
            ভুল অ্যাকাউন্টে আছেন?
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit"
                    style="background: none; border: none; padding: 0; color: #1a7f4b; font-weight: 600; text-decoration: none; cursor: pointer;">
                    লগআউট করুন
                </button>
            </form>
        </div>
    </div>
@endsection
