<?php

namespace App\Services;

use App\Models\Farm;
use App\Support\ReportPeriod;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class ReportService
{
    /** নগদ প্রবাহ: আসলে টাকা পাওয়া-দেওয়ার তারিখ ধরে */
    public function cashFlow(Farm $farm, ReportPeriod $p): array
    {
        $from = $p->fromDate();
        $to = $p->toDate();

        $paymentsOf = fn(string $type) => $farm->payments()
            ->where('payments.status', 'active')
            ->where('payments.payable_type', $type)
            ->whereDate('payments.paid_at', '>=', $from)
            ->whereDate('payments.paid_at', '<=', $to);

        // নগদ আয়: বিক্রির ধরন অনুযায়ী
        $inRows = $paymentsOf('sale')
            ->join('sales', 'sales.id', '=', 'payments.payable_id')
            ->where('sales.status', 'active')
            ->selectRaw('sales.sale_type as grp, sum(payments.amount) as total')
            ->groupBy('sales.sale_type')
            ->get();

        // নগদ ব্যয়: খরচের ধরন অনুযায়ী
        $outRows = $paymentsOf('expense')
            ->join('expenses', 'expenses.id', '=', 'payments.payable_id')
            ->where('expenses.status', 'active')
            ->selectRaw('expenses.category as grp, sum(payments.amount) as total')
            ->groupBy('expenses.category')
            ->get();

        // দিন বা মাস অনুযায়ী (চার্ট ও মোটের জন্য)
        $payRows = $farm->payments()
            ->where('status', 'active')
            ->whereIn('payable_type', ['sale', 'expense'])
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->selectRaw('paid_at as d, payable_type as t, sum(amount) as total')
            ->groupBy('paid_at', 'payable_type')
            ->get();

        // গরু কেনা: কেনার তারিখে পুরোটা বেরিয়ে গেছে ধরা হয়
        $buyRows = $farm->cows()
            ->where('acquisition_type', 'purchased')
            ->where('purchase_price', '>', 0)
            ->whereDate('purchase_date', '>=', $from)
            ->whereDate('purchase_date', '<=', $to)
            ->selectRaw('purchase_date as d, sum(purchase_price) as total')
            ->groupBy('purchase_date')
            ->get();

        $inTotal = round((float) $payRows->where('t', 'sale')->sum('total'), 2);
        $buyTotal = round((float) $buyRows->sum('total'), 2);
        $outTotal = round((float) $payRows->where('t', 'expense')->sum('total') + $buyTotal, 2);

        $saleTypes = config('farm.sale_types');
        $categories = config('farm.expense_categories');

        $inByType = $inRows->map(fn($r) => [
            'key' => $r->grp,
            'label' => $saleTypes[$r->grp]['label'] ?? $r->grp,
            'color' => $saleTypes[$r->grp]['color'] ?? '#8a9c93',
            'total' => round((float) $r->total, 2),
        ])->sortByDesc('total')->values()->all();

        $outByCategory = $outRows->map(fn($r) => [
            'key' => $r->grp,
            'label' => $categories[$r->grp]['label'] ?? $r->grp,
            'color' => $categories[$r->grp]['color'] ?? '#8a9c93',
            'total' => round((float) $r->total, 2),
        ])->all();

        if ($buyTotal > 0) {
            $outByCategory[] = ['key' => 'cow_purchase', 'label' => 'গরু ক্রয়', 'color' => '#8a7a5c', 'total' => $buyTotal];
        }

        usort($outByCategory, fn($a, $b) => $b['total'] <=> $a['total']);

        // সময় অনুযায়ী ভাগ
        [$daily, $keys, $labels] = $this->buckets($p);
        $in = array_fill_keys($keys, 0.0);
        $out = array_fill_keys($keys, 0.0);
        $keyOf = fn($date) => Carbon::parse($date)->format($daily ? 'Y-m-d' : 'Y-m');

        foreach ($payRows as $row) {
            $k = $keyOf($row->d);
            if (! array_key_exists($k, $in)) {
                continue;
            }
            if ($row->t === 'sale') {
                $in[$k] += (float) $row->total;
            } else {
                $out[$k] += (float) $row->total;
            }
        }

        foreach ($buyRows as $row) {
            $k = $keyOf($row->d);
            if (array_key_exists($k, $out)) {
                $out[$k] += (float) $row->total;
            }
        }

        return [
            'in_total' => $inTotal,
            'out_total' => $outTotal,
            'net' => round($inTotal - $outTotal, 2),
            'cow_purchase_total' => $buyTotal,
            'in_by_type' => $inByType,
            'out_by_category' => $outByCategory,
            'series' => [
                'granularity' => $daily ? 'day' : 'month',
                'labels' => $labels,
                'in' => array_map(fn($v) => round($v, 2), array_values($in)),
                'out' => array_map(fn($v) => round($v, 2), array_values($out)),
            ],
        ];
    }

    /** আনুমানিক লাভ-ক্ষতি: আয় ও ব্যয়ের তারিখ ধরে, বকেয়াসহ */
    public function profitLoss(Farm $farm, ReportPeriod $p): array
    {
        $from = $p->fromDate();
        $to = $p->toDate();

        // আয়: বিক্রির ধরন অনুযায়ী
        $revRows = $farm->sales()
            ->where('status', 'active')
            ->whereDate('sold_at', '>=', $from)
            ->whereDate('sold_at', '<=', $to)
            ->selectRaw('sale_type, sum(total_amount) as total, count(*) as cnt')
            ->groupBy('sale_type')
            ->get()
            ->keyBy('sale_type');

        $revenue = [];
        foreach (config('farm.sale_types') as $key => $meta) {
            $revenue[$key] = round((float) ($revRows->get($key)?->total ?? 0), 2);
        }

        $operatingIncome = round($revenue['milk'] + $revenue['manure'] + $revenue['other_income'], 2);

        // ব্যয়: খরচের ধরন অনুযায়ী
        $categories = config('farm.expense_categories');

        $expRows = $farm->expenses()
            ->where('status', 'active')
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->selectRaw('category, sum(total_amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $expenses = $expRows->map(fn($r) => [
            'key' => $r->category,
            'label' => $categories[$r->category]['label'] ?? $r->category,
            'color' => $categories[$r->category]['color'] ?? '#8a9c93',
            'total' => round((float) $r->total, 2),
        ])->values()->all();

        $operatingExpense = round((float) $expRows->sum('total'), 2);
        $operatingResult = round($operatingIncome - $operatingExpense, 2);

        // গরু বিক্রির ফল: বিক্রির টাকা − গরুর ভিত্তিমূল্য
        $cowLines = $farm->sales()
            ->where('sales.sale_type', 'cow')
            ->where('sales.status', 'active')
            ->whereDate('sales.sold_at', '>=', $from)
            ->whereDate('sales.sold_at', '<=', $to)
            ->join('cows', 'cows.id', '=', 'sales.cow_id')
            ->orderBy('sales.sold_at')
            ->orderBy('sales.id')
            ->get([
                'sales.id',
                'sales.sold_at',
                'sales.total_amount',
                'cows.tag_number',
                'cows.acquisition_type',
                'cows.purchase_price',
                'cows.opening_value',
            ])
            ->map(function ($r) {
                $base = match ($r->acquisition_type) {
                    'purchased' => (float) $r->purchase_price,
                    'existing' => (float) $r->opening_value,
                    default => 0.0,
                };

                return [
                    'id' => $r->id,
                    'tag' => $r->tag_number,
                    'sold_at' => $r->sold_at,
                    'price' => round((float) $r->total_amount, 2),
                    'base' => round($base, 2),
                    'result' => round((float) $r->total_amount - $base, 2),
                ];
            })
            ->values();

        $cowSalesTotal = round((float) $cowLines->sum('price'), 2);
        $cowBaseTotal = round((float) $cowLines->sum('base'), 2);
        $cowResult = round($cowSalesTotal - $cowBaseTotal, 2);

        // গরু বাদ পড়ায় ক্ষতি: মারা গেলে বা অন্যত্র সরালে ভিত্তিমূল্য
        $lost = $farm->cows()
            ->whereIn('status', ['dead', 'transferred'])
            ->whereDate('exit_date', '>=', $from)
            ->whereDate('exit_date', '<=', $to)
            ->orderBy('exit_date')
            ->get();

        $lostLines = $lost->map(fn($c) => [
            'id' => $c->id,
            'tag' => $c->tag_number,
            'status' => $c->status,
            'exit_date' => $c->exit_date,
            'base' => round($c->baseValue(), 2),
        ])->filter(fn($l) => $l['base'] > 0)->values();

        $deadTotal = round((float) $lostLines->where('status', 'dead')->sum('base'), 2);
        $transferredTotal = round((float) $lostLines->where('status', 'transferred')->sum('base'), 2);
        $writeOffTotal = round($deadTotal + $transferredTotal, 2);

        // দুধ: উৎপাদন বনাম বিক্রি
        $produced = (float) $farm->milkRecords()
            ->whereDate('milk_date', '>=', $from)
            ->whereDate('milk_date', '<=', $to)
            ->sum('total_liters');

        $milkSold = (float) $farm->sales()
            ->where('sale_type', 'milk')
            ->where('status', 'active')
            ->whereDate('sold_at', '>=', $from)
            ->whereDate('sold_at', '<=', $to)
            ->sum('quantity');

        return [
            'revenue' => $revenue,
            'operating_income' => $operatingIncome,
            'expenses' => $expenses,
            'operating_expense' => $operatingExpense,
            'operating_result' => $operatingResult,
            'cow_lines' => $cowLines->all(),
            'cow_sales_total' => $cowSalesTotal,
            'cow_base_total' => $cowBaseTotal,
            'cow_result' => $cowResult,
            'write_offs' => [
                'dead' => $deadTotal,
                'transferred' => $transferredTotal,
                'total' => $writeOffTotal,
                'lines' => $lostLines->all(),
            ],
            'net' => round($operatingResult + $cowResult - $writeOffTotal, 2),
            'milk' => [
                'produced' => round($produced, 2),
                'sold' => round($milkSold, 2),
                'revenue' => $revenue['milk'],
                'avg_price' => $milkSold > 0 ? round($revenue['milk'] / $milkSold, 2) : null,
            ],
        ];
    }

    /** ৩১ দিন বা কম হলে দিনভিত্তিক, বেশি হলে মাসভিত্তিক */
    private function buckets(ReportPeriod $p): array
    {
        $daily = $p->from->diffInDays($p->to) <= 31;
        $names = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রি', 'মে', 'জুন', 'জুলা', 'আগ', 'সেপ্টে', 'অক্টো', 'নভে', 'ডিসে'];
        $keys = [];
        $labels = [];

        if ($daily) {
            foreach (CarbonPeriod::create($p->from, '1 day', $p->to) as $d) {
                $keys[] = $d->format('Y-m-d');
                $labels[] = $d->format('d/m');
            }
        } else {
            foreach (CarbonPeriod::create($p->from->copy()->startOfMonth(), '1 month', $p->to) as $d) {
                $keys[] = $d->format('Y-m');
                $labels[] = $names[$d->month - 1] . ' ' . $d->format('y');
            }
        }

        return [$daily, $keys, $labels];
    }
}
