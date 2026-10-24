<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ড্যাশবোর্ড') | {{ config('app.name') }}</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('image/favicon.ico') }}">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @livewireStyles
</head>

<body x-data="{
    nav: false,
    rail: localStorage.getItem('rail') === '1',
    toggleRail() {
        this.rail = !this.rail;
        localStorage.setItem('rail', this.rail ? '1' : '0');
    }
}" @keydown.escape.window="nav = false">

    <div class="app-overlay" x-show="nav" x-transition.opacity @click="nav = false" style="display: none"></div>

    <div class="app-shell" :class="{ 'is-rail': rail }">

        {{-- ===== Sidebar ===== --}}
        <aside class="app-sidebar" :class="{ 'is-open': nav }" aria-label="প্রধান মেনু">
            <div class="sb-top">
                <a class="sb-brand" href="{{ route('farms.index') }}">
                    <img src="{{ asset('image/logo.png') }}" alt="Gen Z Agro" class="app-logo"
                        style="object-fit: contain; width: 40px; height: 40px; border-radius: 50%; background: white;">
                    <strong class="sb-label">Gen Z Agro</strong>
                </a>
                <button type="button" class="icon-btn d-none d-lg-grid" @click="toggleRail()"
                    aria-label="মেনু ছোট বা বড় করুন">
                    <i class="bi" :class="rail ? 'bi-chevron-double-right' : 'bi-chevron-double-left'"></i>
                </button>
                <button type="button" class="icon-btn d-lg-none" @click="nav = false" aria-label="মেনু বন্ধ করুন">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            @isset($currentFarm)
                <div class="farm-card">
                    <div class="farm-card-label">বর্তমান খামার</div>
                    <div class="farm-card-name">{{ $currentFarm->name }}</div>
                    @isset($subscription)
                        <div class="farm-card-meta">
                            <span class="chip">{{ $subscription->plan->name }}</span>
                            <span class="chip chip-{{ $subscription->badgeClass() }}">{{ $subscription->statusLabel() }}</span>
                        </div>
                    @endisset
                    <a class="farm-card-link" href="{{ route('farms.index') }}">খামার পরিবর্তন করুন</a>
                </div>
            @endisset

            <nav class="sb-scroll">
                @isset($currentFarm)
                    <a class="sb-link {{ request()->routeIs('farms.dashboard') ? 'active' : '' }}" title="ড্যাশবোর্ড"
                        href="{{ route('farms.dashboard', $currentFarm) }}">
                        <i class="bi bi-grid-1x2"></i><span class="sb-label">ড্যাশবোর্ড</span>
                    </a>

                    {{-- পরের Phase-এ এগুলো লিংক হয়ে যাবে --}}
                    <a class="sb-link {{ request()->routeIs('farms.cows.*') ? 'active' : '' }}" title="গরু ও বাছুর"
                        href="{{ route('farms.cows.index', $currentFarm) }}">
                        <i class="bi bi-collection"></i><span class="sb-label">গরু ও বাছুর</span>
                    </a>
                    <a class="sb-link {{ request()->routeIs('farms.milk.*') ? 'active' : '' }}" title="দুধ উৎপাদন"
                        href="{{ route('farms.milk.index', $currentFarm) }}">
                        <i class="bi bi-droplet-half"></i><span class="sb-label">দুধ উৎপাদন</span>
                    </a>
                    <a class="sb-link {{ request()->routeIs('farms.health.*') ? 'active' : '' }}" title="চিকিৎসা ও টিকা"
                        href="{{ route('farms.health.index', $currentFarm) }}">
                        <i class="bi bi-heart-pulse"></i><span class="sb-label">চিকিৎসা ও টিকা</span>
                    </a>
                    <a class="sb-link {{ request()->routeIs('farms.breeding.*') ? 'active' : '' }}" title="প্রজনন"
                        href="{{ route('farms.breeding.index', $currentFarm) }}">
                        <i class="bi bi-hearts"></i><span class="sb-label">প্রজনন</span>
                    </a>
                    <a class="sb-link {{ request()->routeIs('farms.feed.*') ? 'active' : '' }}" title="খাবার"
                        href="{{ route('farms.feed.index', $currentFarm) }}">
                        <i class="bi bi-basket"></i><span class="sb-label">খাবার</span>
                    </a>

                    <div class="sb-title">হিসাব</div>
                    @can('manageFinance', $currentFarm)
                        <a class="sb-link {{ request()->routeIs('farms.expenses.*') || request()->routeIs('farms.payments.*') ? 'active' : '' }}"
                            title="খরচ" href="{{ route('farms.expenses.index', $currentFarm) }}">
                            <i class="bi bi-receipt"></i><span class="sb-label">খরচ</span>
                        </a>
                    @endcan
                    @can('manageFinance', $currentFarm)
                        <a class="sb-link {{ request()->routeIs('farms.sales.*') ? 'active' : '' }}" title="আয় ও বিক্রি"
                            href="{{ route('farms.sales.index', $currentFarm) }}">
                            <i class="bi bi-cash-stack"></i><span class="sb-label">আয় ও বিক্রি</span>
                        </a>
                        <a class="sb-link {{ request()->routeIs('farms.customers.*') ? 'active' : '' }}"
                            title="ক্রেতা ও বকেয়া" href="{{ route('farms.customers.index', $currentFarm) }}">
                            <i class="bi bi-person-lines-fill"></i><span class="sb-label">ক্রেতা ও বকেয়া</span>
                        </a>
                    @endcan
                    <span class="sb-link is-disabled" title="রিপোর্ট"><i class="bi bi-graph-up-arrow"></i><span
                            class="sb-label">রিপোর্ট</span><em class="soon">শীঘ্রই</em></span>

                    <div class="sb-title">ব্যবস্থাপনা</div>
                    @can('manageMembers', $currentFarm)
                        <a class="sb-link {{ request()->routeIs('farms.members.*') ? 'active' : '' }}" title="সদস্য"
                            href="{{ route('farms.members.index', $currentFarm) }}"><i class="bi bi-people"></i><span
                                class="sb-label">সদস্য</span></a>
                    @endcan
                    @can('manageBilling', $currentFarm)
                        <a class="sb-link {{ request()->routeIs('farms.billing.*') ? 'active' : '' }}" title="সাবস্ক্রিপশন"
                            href="{{ route('farms.billing.index', $currentFarm) }}"><i class="bi bi-credit-card"></i><span
                                class="sb-label">সাবস্ক্রিপশন</span></a>
                    @endcan
                    @can('manageNotifications', $currentFarm)
                        <a class="sb-link {{ request()->routeIs('farms.notifications.*') ? 'active' : '' }}"
                            title="SMS বিজ্ঞপ্তি" href="{{ route('farms.notifications.edit', $currentFarm) }}"><i
                                class="bi bi-chat-dots"></i><span class="sb-label">SMS বিজ্ঞপ্তি</span></a>
                    @endcan
                    @can('update', $currentFarm)
                        <a class="sb-link {{ request()->routeIs('farms.edit') ? 'active' : '' }}" title="খামারের সেটিংস"
                            href="{{ route('farms.edit', $currentFarm) }}"><i class="bi bi-gear"></i><span
                                class="sb-label">খামারের সেটিংস</span></a>
                    @endcan
                @endisset

                <div class="sb-title">অ্যাকাউন্ট</div>
                <a class="sb-link {{ request()->routeIs('farms.index') ? 'active' : '' }}" title="আমার খামারসমূহ"
                    href="{{ route('farms.index') }}"><i class="bi bi-buildings"></i><span class="sb-label">আমার
                        খামারসমূহ</span></a>

                @if (auth()->user()->is_super_admin)
                    <div class="sb-title">অ্যাডমিন</div>
                    <a class="sb-link {{ request()->routeIs('admin.index') ? 'active' : '' }}" title="সব খামার"
                        href="{{ route('admin.index') }}"><i class="bi bi-shield-lock"></i><span class="sb-label">সব
                            খামার</span></a>
                    <a class="sb-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}"
                        title="পেমেন্ট যাচাই" href="{{ route('admin.payments.index') }}"><i
                            class="bi bi-cash-coin"></i><span class="sb-label">পেমেন্ট যাচাই</span></a>
                    <a class="sb-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" title="প্ল্যান"
                        href="{{ route('admin.plans.index') }}"><i class="bi bi-layers"></i><span
                            class="sb-label">প্ল্যান</span></a>
                @endif
            </nav>
        </aside>

        {{-- ===== Main ===== --}}
        <div class="app-main">
            <header class="app-header">
                <button type="button" class="icon-btn d-lg-none" @click="nav = true" aria-label="মেনু খুলুন">
                    <i class="bi bi-list"></i>
                </button>

                <div>
                    <div class="crumbs">{{ isset($currentFarm) ? $currentFarm->name : 'Gen Z Agro' }}</div>
                    <h2 class="page-title">@yield('title', '')</h2>
                </div>

                <div class="ms-auto dropdown">
                    <button type="button" class="user-chip" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                        <span class="d-none d-sm-inline fw-medium">{{ auth()->user()->name }}</span>
                        <i class="bi bi-chevron-down small text-muted"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-1 small text-muted">{{ auth()->user()->email }}</li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>লগআউট</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="app-content">
                @if (session('success'))
                    <div class="alert alert-success d-flex justify-content-between" x-data="{ show: true }"
                        x-show="show">
                        <span>{{ session('success') }}</span>
                        <button type="button" class="btn-close" @click="show = false"
                            aria-label="বন্ধ করুন"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @include('layouts._subscription_banner')

                @yield('content')
            </main>
        </div>
    </div>

    {{-- ===== মোবাইলের নিচের মেনু ===== --}}
    <nav class="bottom-nav d-lg-none" aria-label="দ্রুত মেনু">
        @isset($currentFarm)
            <a class="{{ request()->routeIs('farms.dashboard') ? 'active' : '' }}"
                href="{{ route('farms.dashboard', $currentFarm) }}">
                <i class="bi bi-house-door"></i><span>হোম</span>
            </a>
            <a class="{{ request()->routeIs('farms.cows.*') ? 'active' : '' }}"
                href="{{ route('farms.cows.index', $currentFarm) }}">
                <i class="bi bi-collection"></i><span>গরু</span>
            </a>
            <a class="{{ request()->routeIs('farms.milk.*') ? 'active' : '' }}"
                href="{{ route('farms.milk.index', $currentFarm) }}">
                <i class="bi bi-droplet-half"></i><span>দুধ</span>
            </a>
            @can('manageFinance', $currentFarm)
                <a class="{{ request()->routeIs('farms.expenses.*') ? 'active' : '' }}"
                    href="{{ route('farms.expenses.index', $currentFarm) }}">
                    <i class="bi bi-receipt"></i><span>খরচ</span>
                </a>
            @else
                <span class="is-disabled"><i class="bi bi-receipt"></i><span>খরচ</span></span>
            @endcan
        @else
            <a class="{{ request()->routeIs('farms.index') ? 'active' : '' }}" href="{{ route('farms.index') }}">
                <i class="bi bi-buildings"></i><span>খামার</span>
            </a>
            <span></span><span></span><span></span>
        @endisset
        <button type="button" @click="nav = true"><i class="bi bi-grid"></i><span>আরও</span></button>
    </nav>

    @livewireScripts
</body>

</html>
