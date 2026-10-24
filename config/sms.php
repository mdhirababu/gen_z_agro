<?php

return [
    // log: আসলে পাঠায় না, লগে লেখে। http: নিচের গেটওয়ে সেটিং অনুযায়ী পাঠায়।
    'driver' => env('SMS_DRIVER', 'log'),

    // গেটওয়ে কোন ধাঁচে নম্বর চায়: international (8801XXXXXXXXX) নাকি local (01XXXXXXXXX)
    'number_format' => env('SMS_NUMBER_FORMAT', 'international'),

    // খামারপ্রতি মাসে সর্বোচ্চ কত segment (বাংলা SMS-এ ৭০ অক্ষরে ১ segment)
    'monthly_segment_limit' => (int) env('SMS_MONTHLY_SEGMENT_LIMIT', 200),

    'http' => [
        'method' => env('SMS_HTTP_METHOD', 'POST'),       // GET বা POST
        'url' => env('SMS_HTTP_URL'),
        'body_format' => env('SMS_HTTP_BODY', 'form'),    // form বা json (POST-এর জন্য)
        'timeout' => 15,

        // তোমার গেটওয়ে নম্বর ও বার্তার জন্য যে প্যারামিটারের নাম চায়
        'fields' => [
            'to' => env('SMS_FIELD_TO', 'number'),
            'message' => env('SMS_FIELD_MESSAGE', 'message'),
        ],

        // প্রতিবার যে বাড়তি প্যারামিটার যাবে (api key, sender id ইত্যাদি), JSON আকারে
        'static' => json_decode((string) env('SMS_HTTP_STATIC', '{}'), true) ?: [],

        // রেসপন্সে এই লেখা থাকলে সফল ধরা হবে। ফাঁকা রাখলে শুধু HTTP 2xx দেখা হবে।
        'success_contains' => env('SMS_SUCCESS_CONTAINS'),
    ],
];
