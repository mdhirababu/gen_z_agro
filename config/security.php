<?php

return [
    // false করলে সব security header বন্ধ
    'headers' => env('SECURITY_HEADERS', true),

    // off: কিছু পাঠায় না | report-only: শুধু ব্রাউজারের console-এ সতর্কতা | enforce: আসলে আটকায়
    'csp_mode' => env('CSP_MODE', 'report-only'),

    // নিজের সাইটের বাইরের কোন উৎস অনুমোদন করবে, যেমন 'https://cdn.example.com'
    'csp' => [
        'script-src' => [],
        'style-src' => [],
        'font-src' => [],
        'img-src' => [],
        'connect-src' => [],
    ],

    // HTTPS-এ থাকলে ব্রাউজারকে সবসময় HTTPS ব্যবহার করতে বলে (প্রোডাকশনে চালু)
    'hsts' => env('HSTS_ENABLED', env('APP_ENV') === 'production'),
    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 15552000), // ১৮০ দিন

    // লগইনের বাইরের ফর্মে IP প্রতি সীমা: [সর্বোচ্চ চেষ্টা, কত মিনিটে]
    'auth_throttle' => [
        'register' => [20, 60],
        'password/email' => [6, 10],
        'password/reset' => [10, 10],
    ],
];
