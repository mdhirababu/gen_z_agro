<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'লগইন') | {{ config('app.name') }}</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('image/favicon.ico') }}">
    {{-- 🔥 Bootstrap Icons যোগ করুন (এটি ছাড়া আইকন কাজ করবে না) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    {{-- Auth page brand/logo styles --}}
    <style>
        /* ===== Auth Brand (logo + text, centered column) ===== */
        .auth-brand {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 12px !important;
            text-align: center !important;
            width: 100% !important;
            margin-bottom: 24px !important;
            /* হেডিং এর সাথে একটু দূরত্ব */
        }

        /* লোগোকে সার্কেল করা এবং সাদা ব্যাকগ্রাউন্ড দেওয়া */
        .auth-brand .app-logo {
            width: 100px !important;
            /* লোগোর সাইজ আরও বড় করা হলো */
            height: 100px !important;
            object-fit: cover !important;
            /* ছবি যাতে সার্কেলে পুরোপুরি ফিট হয় */
            display: block !important;
            margin: 0 auto !important;
            border-radius: 50% !important;
            /* সার্কেল বা রাউন্ড শেইপ */
            background-color: #ffffff !important;
            /* সাদা ব্যাকগ্রাউন্ড (ছবির ভেতরের সাদা অংশের সাথে ম্যাচ করতে) */
            padding: 6px !important;
            /* লোগো এবং বর্ডারের মাঝে একটু ফাঁকা জায়গা */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
            /* হালকা শ্যাডো (প্রিমিয়াম লুকের জন্য) */
            border: 2px solid rgba(255, 255, 255, 0.2) !important;
            /* হালকা বর্ডার */
        }

        /* Gen Z Agro টেক্সট সাদা করা হলো */
        .auth-brand span {
            display: block !important;
            font-size: 1.25rem !important;
            /* টেক্সট একটু বড় করা হলো */
            font-weight: 700 !important;
            letter-spacing: 0.5px !important;
            color: #ffffff !important;
            /* টেক্সট কালার সাদা */
            line-height: 1.2 !important;
            margin: 0 !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            /* টেক্সটে হালকা শ্যাডো */
        }

        /* মোবাইলে লোগো এবং টেক্সট একটু ছোট হবে */
        @media (max-width: 576px) {
            .auth-brand .app-logo {
                width: 80px !important;
                height: 80px !important;
            }

            .auth-brand span {
                font-size: 1.15rem !important;
            }
        }
    </style>

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>

<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <section class="auth-hero">
                <div class="auth-brand">
                    {{-- এখানে object-fit: cover এর কারণে ছবির সাদা বক্সগুলো কেটে যাবে এবং পারফেক্ট সার্কেল হবে --}}
                    <img src="{{ asset('image/logo.png') }}" alt="Gen Z Agro" class="app-logo">
                    <span>Gen Z Agro</span>
                </div>

                <h1>গরুর খামারের হিসাব, এক জায়গায়</h1>
                <p>খরচ, আয়, সদস্য আর সাবস্ক্রিপশন সহজে দেখুন, কম্পিউটার বা মোবাইল থেকে।</p>

                <ul class="auth-points">
                    <li><span class="pt"><i class="bi bi-shield-check"></i></span>প্রতিটি খামারের তথ্য আলাদা ও
                        নিরাপদ</li>
                    <li><span class="pt"><i class="bi bi-people"></i></span>মালিক, ম্যানেজার ও স্টাফের আলাদা অনুমতি
                    </li>
                    <li><span class="pt"><i class="bi bi-phone"></i></span>কম্পিউটার ও মোবাইল, দুটোতেই চলে</li>
                </ul>
            </section>

            <section class="auth-form">
                @yield('content')
            </section>
        </div>
    </div>

    @livewireScripts
</body>

</html>
