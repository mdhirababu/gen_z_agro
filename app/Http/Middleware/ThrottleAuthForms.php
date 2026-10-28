<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAuthForms
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST')) {
            foreach ((array) config('security.auth_throttle', []) as $path => [$max, $minutes]) {
                if (! $request->is($path)) {
                    continue;
                }

                $key = 'auth-form:' . $path . ':' . $request->ip();

                if (RateLimiter::tooManyAttempts($key, $max)) {
                    abort(429, 'অনেকবার চেষ্টা হয়েছে। কিছুক্ষণ পরে আবার চেষ্টা করুন।', [
                        'Retry-After' => RateLimiter::availableIn($key),
                    ]);
                }

                RateLimiter::hit($key, $minutes * 60);
            }
        }

        return $next($request);
    }
}
