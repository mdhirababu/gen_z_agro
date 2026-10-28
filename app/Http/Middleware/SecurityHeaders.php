<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers', true)) {
            return $response;
        }

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if (config('security.hsts') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=' . (int) config('security.hsts_max_age'));
        }

        $mode = config('security.csp_mode');

        if ($mode === 'enforce') {
            $headers->set('Content-Security-Policy', $this->policy());
        } elseif ($mode === 'report-only') {
            $headers->set('Content-Security-Policy-Report-Only', $this->policy());
        }

        // সাইন-ইন করা ব্যবহারকারীর HTML পাতা ব্রাউজারে জমা রাখা হয় না,
        // শেয়ার করা কম্পিউটারে লগআউটের পর "Back" চেপে হিসাব দেখা যাবে না
        if ($request->user() && str_contains((string) $headers->get('Content-Type'), 'text/html')) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    private function policy(): string
    {
        $extra = fn(string $key): array => (array) config("security.csp.{$key}", []);
        $dev = $this->devServer();

        $directives = [
            'default-src' => ["'self'"],
            // 'unsafe-inline' ও 'unsafe-eval' লাগে Livewire-এর Alpine ও পাতার inline কোডের জন্য
            'script-src' => array_merge(["'self'", "'unsafe-inline'", "'unsafe-eval'"], $extra('script-src'), $dev['http']),
            'style-src' => array_merge(["'self'", "'unsafe-inline'"], $extra('style-src'), $dev['http']),
            'img-src' => array_merge(["'self'", 'data:', 'blob:'], $extra('img-src')),
            'font-src' => array_merge(["'self'", 'data:'], $extra('font-src'), $dev['http']),
            'connect-src' => array_merge(["'self'"], $extra('connect-src'), $dev['http'], $dev['ws']),
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn(array $sources, string $name) => $name . ' ' . implode(' ', array_unique($sources)))
            ->implode('; ');
    }

    /** লোকালে `npm run dev` (Vite) চললে তার ঠিকানা অনুমোদন করা */
    private function devServer(): array
    {
        $hot = public_path('hot');

        if (! app()->environment('local') || ! is_file($hot)) {
            return ['http' => [], 'ws' => []];
        }

        $origin = rtrim(trim((string) file_get_contents($hot)), '/');

        if (! preg_match('#^https?://[^/\s]+$#', $origin)) {
            return ['http' => [], 'ws' => []];
        }

        return ['http' => [$origin], 'ws' => [preg_replace('#^http#', 'ws', $origin)]];
    }
}
