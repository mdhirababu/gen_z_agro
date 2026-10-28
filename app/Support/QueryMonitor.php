<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class QueryMonitor
{
    /** ধীর query লগে যায়। বাইন্ডিং (মান) ইচ্ছে করে লেখা হয় না, তাতে ব্যক্তিগত তথ্য থাকতে পারে। */
    public static function handle(QueryExecuted $query): void
    {
        $limit = (float) config('ops.slow_query_ms', 500);

        if ($limit <= 0 || $query->time < $limit) {
            return;
        }

        Log::warning('slow query', [
            'ms' => round($query->time, 1),
            'sql' => Str::limit($query->sql, 600, '…'),
            'path' => request()->path(),
        ]);
    }
}
