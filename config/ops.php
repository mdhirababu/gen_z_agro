<?php

return [
    // এর চেয়ে ধীর query লগে যাবে (মিলিসেকেন্ড)। 0 দিলে বন্ধ।
    'slow_query_ms' => (float) env('SLOW_QUERY_MS', 500),

    // ব্যাকআপ এর চেয়ে পুরোনো হলে ops:check ব্যর্থ ধরবে (ঘণ্টা)
    'backup_max_hours' => (int) env('OPS_BACKUP_MAX_HOURS', 36),

    // scheduler-এর হৃৎস্পন্দন এর চেয়ে পুরোনো হলে cron বন্ধ ধরা হবে (মিনিট)
    'heartbeat_max_minutes' => (int) env('OPS_HEARTBEAT_MAX_MINUTES', 5),

    // ডিস্কে কমপক্ষে এত GB খালি না থাকলে সতর্কতা
    'min_free_gb' => (float) env('OPS_MIN_FREE_GB', 1),
];
