<!DOCTYPE html>
<html lang="bn">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('app.name') }}</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>

<body class="bg-light">
    <main class="container py-4" style="max-width: 820px">
        <a href="{{ url('/') }}" class="small">← {{ config('app.name') }}</a>

        <article class="card shadow-sm mt-3">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">@yield('title')</h1>
                @yield('content')
            </div>
        </article>

        <p class="text-center small text-muted mt-3">
            <a href="{{ route('privacy') }}">গোপনীয়তা নীতি</a> · <a href="{{ route('terms') }}">শর্তাবলী</a>
        </p>
    </main>
</body>

</html>
