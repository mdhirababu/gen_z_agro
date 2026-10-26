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

    /**
     * গরুভিত্তিক লাভ-ক্ষতি (সময়কালের)।
     * আয়: দুধের আয় (উৎপাদনের অনুপাতে ভাগ) + গরু বিক্রির টাকা
     * ব্যয়: সরাসরি খরচ + যৌথ খরচের ভাগ + (বিক্রি বা বাদ পড়লে) গরুর ভিত্তিমূল্য
     * ভাগ না হওয়া আয়-ব্যয় আলাদা রেখে যোগফল লাভ-ক্ষতি রিপোর্টের নিট ফলের সঙ্গে মেলে।
     */
    public function cowProfitability(Farm $farm, ReportPeriod $p): array
    {
        $from = $p->from->copy()->startOfDay();
        $to = $p->to->copy()->startOfDay();
        $fromS = $p->fromDate();
        $toS = $p->toDate();
        $categories = config('farm.expense_categories');

        // ---- গরু ও সময়কালে খামারে থাকার দিন ----
        $info = [];

        foreach ($farm->cows()->get() as $cow) {
            $start = $cow->onFarmFrom();
            $end = $cow->exit_date ? $cow->exit_date->copy()->startOfDay() : null;

            $a = $start->gt($from) ? $start->copy() : $from->copy();
            $b = ($end && $end->lt($to)) ? $end->copy() : $to->copy();

            $info[$cow->id] = [
                'cow' => $cow,
                'start' => $start,
                'end' => $end,
                'days' => $a->lte($b) ? (int) $a->diffInDays($b) + 1 : 0,
            ];
        }

        $blank = [
            'liters' => 0.0,
            'income_milk' => 0.0,
            'income_sale' => 0.0,
            'cost_direct' => 0.0,
            'joint_feed' => 0.0,
            'joint_other' => 0.0,
            'cost_base' => 0.0,
        ];
        $rows = array_map(fn() => $blank, $info);

        $unIncomeLines = [];
        $unExpenseLines = [];

        // ---- ভাগের ওজন ----
        $weightsDays = array_map(fn($i) => (float) $i['days'], $info);
        $weightsEqual = array_map(fn($i) => $i['days'] > 0 ? 1.0 : 0.0, $info);
        $weightsFeed = $this->feedWeights($farm, $info, $fromS, $toS);

        $feedFallback = false;
        $weightsFor = function (string $category) use ($weightsDays, $weightsEqual, $weightsFeed, &$feedFallback): array {
            $rule = $category === 'feed' ? config('farm.allocation.feed') : config('farm.allocation.other');

            if ($rule === 'equal') {
                return $weightsEqual;
            }

            if ($category === 'feed' && $rule === 'feed_records') {
                if (array_sum($weightsFeed) > 0) {
                    return $weightsFeed;
                }

                $feedFallback = true; // খাবারের রেকর্ড (বা দাম) নেই, গরু-দিনে ফিরে যাওয়া হলো
            }

            return $weightsDays;
        };

        // ---- যৌথ খরচ (কোনো গরুর নামে নেই) ----
        $joint = $farm->expenses()
            ->where('status', 'active')
            ->whereNull('cow_id')
            ->whereDate('expense_date', '>=', $fromS)
            ->whereDate('expense_date', '<=', $toS)
            ->selectRaw('category, sum(total_amount) as total')
            ->groupBy('category')
            ->get();

        foreach ($joint as $row) {
            $total = (float) $row->total;
            $parts = $this->split($total, $weightsFor((string) $row->category));

            if ($parts === null) {
                $unExpenseLines[] = [
                    'label' => ($categories[$row->category]['label'] ?? $row->category) . ' (এই সময়ে কোনো গরু ছিল না)',
                    'amount' => $total,
                ];
                continue;
            }

            $key = $row->category === 'feed' ? 'joint_feed' : 'joint_other';

            foreach ($parts as $id => $amount) {
                $rows[$id][$key] += $amount;
            }
        }

        // ---- সরাসরি খরচ (গরুর নামে) ----
        $orphanDirect = 0.0;

        $directRows = $farm->expenses()
            ->where('status', 'active')
            ->whereNotNull('cow_id')
            ->whereDate('expense_date', '>=', $fromS)
            ->whereDate('expense_date', '<=', $toS)
            ->selectRaw('cow_id, sum(total_amount) as total')
            ->groupBy('cow_id')
            ->get();

        foreach ($directRows as $row) {
            if (isset($rows[$row->cow_id])) {
                $rows[$row->cow_id]['cost_direct'] += (float) $row->total;
            } else {
                $orphanDirect += (float) $row->total;
            }
        }

        if ($orphanDirect > 0) {
            $unExpenseLines[] = ['label' => 'মুছে ফেলা গরুর সরাসরি খরচ', 'amount' => $orphanDirect];
        }

        // ---- দুধের আয়: উৎপাদনের অনুপাতে ----
        $milkRevenue = (float) $farm->sales()
            ->where('sale_type', 'milk')
            ->where('status', 'active')
            ->whereDate('sold_at', '>=', $fromS)
            ->whereDate('sold_at', '<=', $toS)
            ->sum('total_amount');

        $production = $farm->milkRecords()
            ->whereDate('milk_date', '>=', $fromS)
            ->whereDate('milk_date', '<=', $toS)
            ->selectRaw('cow_id, sum(total_liters) as liters')
            ->groupBy('cow_id')
            ->get();

        $totalLiters = (float) $production->sum('liters');

        foreach ($production as $row) {
            if (isset($rows[$row->cow_id])) {
                $rows[$row->cow_id]['liters'] = (float) $row->liters;
            }
        }

        if ($milkRevenue > 0) {
            if ($totalLiters > 0) {
                $orphanMilk = 0.0;

                foreach ($production as $row) {
                    $share = $milkRevenue * (float) $row->liters / $totalLiters;

                    if (isset($rows[$row->cow_id])) {
                        $rows[$row->cow_id]['income_milk'] += $share;
                    } else {
                        $orphanMilk += $share;
                    }
                }

                if ($orphanMilk > 0) {
                    $unIncomeLines[] = ['label' => 'মুছে ফেলা গরুর দুধের আয়', 'amount' => $orphanMilk];
                }
            } else {
                $unIncomeLines[] = ['label' => 'দুধ বিক্রির আয় (উৎপাদনের রেকর্ড নেই বলে ভাগ করা যায়নি)', 'amount' => $milkRevenue];
            }
        }

        // ---- গোবর ও অন্যান্য আয়: কোনো গরুর নয় ----
        $otherIncome = (float) $farm->sales()
            ->whereIn('sale_type', ['manure', 'other_income'])
            ->where('status', 'active')
            ->whereDate('sold_at', '>=', $fromS)
            ->whereDate('sold_at', '<=', $toS)
            ->sum('total_amount');

        if ($otherIncome > 0) {
            $unIncomeLines[] = ['label' => 'গোবর ও অন্যান্য আয় (কোনো নির্দিষ্ট গরুর নয়)', 'amount' => $otherIncome];
        }

        // ---- গরু বিক্রি: টাকা আয়, ভিত্তিমূল্য খরচ ----
        $saleRows = $farm->sales()
            ->where('sales.sale_type', 'cow')
            ->where('sales.status', 'active')
            ->whereDate('sales.sold_at', '>=', $fromS)
            ->whereDate('sales.sold_at', '<=', $toS)
            ->join('cows', 'cows.id', '=', 'sales.cow_id')
            ->get(['sales.cow_id', 'sales.total_amount', 'cows.acquisition_type', 'cows.purchase_price', 'cows.opening_value']);

        $orphanSalePrice = 0.0;
        $orphanSaleBase = 0.0;

        foreach ($saleRows as $row) {
            $base = match ($row->acquisition_type) {
                'purchased' => (float) $row->purchase_price,
                'existing' => (float) $row->opening_value,
                default => 0.0,
            };

            if (isset($rows[$row->cow_id])) {
                $rows[$row->cow_id]['income_sale'] += (float) $row->total_amount;
                $rows[$row->cow_id]['cost_base'] += $base;
            } else {
                $orphanSalePrice += (float) $row->total_amount;
                $orphanSaleBase += $base;
            }
        }

        if ($orphanSalePrice > 0) {
            $unIncomeLines[] = ['label' => 'মুছে ফেলা গরুর বিক্রির টাকা', 'amount' => $orphanSalePrice];
        }
        if ($orphanSaleBase > 0) {
            $unExpenseLines[] = ['label' => 'মুছে ফেলা গরুর ভিত্তিমূল্য', 'amount' => $orphanSaleBase];
        }

        // ---- মারা গেলে বা সরালে ভিত্তিমূল্য ক্ষতি ----
        foreach ($info as $id => $i) {
            $cow = $i['cow'];

            if (in_array($cow->status, ['dead', 'transferred'], true) && $cow->exit_date) {
                $exit = $cow->exit_date->toDateString();

                if ($exit >= $fromS && $exit <= $toS) {
                    $rows[$id]['cost_base'] += $cow->baseValue();
                }
            }
        }

        // ---- ফলাফল ----
        $out = [];
        $rawResult = 0.0;

        foreach ($info as $id => $i) {
            $r = $rows[$id];
            $income = $r['income_milk'] + $r['income_sale'];
            $jointSum = $r['joint_feed'] + $r['joint_other'];
            $cost = $r['cost_direct'] + $jointSum + $r['cost_base'];

            if ($i['days'] === 0 && $income == 0.0 && $cost == 0.0 && $r['liters'] == 0.0) {
                continue;
            }

            $rawResult += $income - $cost;

            $out[] = [
                'cow' => $i['cow'],
                'days' => $i['days'],
                'liters' => round($r['liters'], 2),
                'income_milk' => round($r['income_milk'], 2),
                'income_sale' => round($r['income_sale'], 2),
                'income' => round($income, 2),
                'cost_direct' => round($r['cost_direct'], 2),
                'joint_feed' => round($r['joint_feed'], 2),
                'joint_other' => round($r['joint_other'], 2),
                'joint' => round($jointSum, 2),
                'cost_base' => round($r['cost_base'], 2),
                'cost' => round($cost, 2),
                'result' => round($income - $cost, 2),
            ];
        }

        $totals = [];
        foreach (['income', 'cost_direct', 'joint', 'cost_base', 'cost', 'result'] as $key) {
            $totals[$key] = round((float) array_sum(array_column($out, $key)), 2);
        }

        $unIncome = (float) array_sum(array_column($unIncomeLines, 'amount'));
        $unExpense = (float) array_sum(array_column($unExpenseLines, 'amount'));

        return [
            'rows' => $out,
            'totals' => $totals,
            'unallocated' => [
                'income' => round($unIncome, 2),
                'expense' => round($unExpense, 2),
                'income_lines' => $unIncomeLines,
                'expense_lines' => $unExpenseLines,
            ],
            // গরুর ফলের যোগফল + ভাগ না হওয়া আয় − ভাগ না হওয়া খরচ = লাভ-ক্ষতির নিট ফল
            'check_net' => round($rawResult + $unIncome - $unExpense, 2),
            'rules' => [
                'feed' => (string) config('farm.allocation.feed'),
                'other' => (string) config('farm.allocation.other'),
            ],
            'feed_fallback' => $feedFallback,
        ];
    }

    /** যৌথ খাবারের খরচ ভাগের ওজন: খাবারের রেকর্ডের (পরিমাণ × দাম) মান, গরু ধরে ধরে */
    private function feedWeights(Farm $farm, array $info, string $from, string $to): array
    {
        $weights = array_fill_keys(array_keys($info), 0.0);

        $records = $farm->feedRecords()
            ->join('feed_types', 'feed_types.id', '=', 'feed_records.feed_type_id')
            ->whereDate('feed_records.fed_on', '>=', $from)
            ->whereDate('feed_records.fed_on', '<=', $to)
            ->get([
                'feed_records.fed_on',
                'feed_records.quantity',
                'feed_records.target',
                'feed_records.category',
                'feed_records.cow_id',
                'feed_types.price_per_unit',
            ]);

        foreach ($records as $record) {
            $value = (float) $record->quantity * (float) $record->price_per_unit;

            if ($value <= 0) {
                continue; // দাম দেওয়া নেই, ভাগের হিসাবে ধরা যায় না
            }

            if ($record->target === 'cow') {
                if (isset($weights[$record->cow_id])) {
                    $weights[$record->cow_id] += $value;
                }
                continue;
            }

            $date = $record->fed_on->copy()->startOfDay();

            // সেদিন খামারে ছিল এমন গরুদের মধ্যে সমান ভাগ (ধরন বেছে দিলে শুধু সেই ধরনের গরু)
            $eligible = array_filter($info, fn($i) => $i['start']->lte($date)
                && (! $i['end'] || $i['end']->gte($date))
                && ($record->target !== 'category' || $i['cow']->category === $record->category));

            if (! $eligible) {
                continue;
            }

            $share = $value / count($eligible);

            foreach ($eligible as $id => $_) {
                $weights[$id] += $share;
            }
        }

        return $weights;
    }

    /** $total-কে $weights অনুপাতে ভাগ করে। ওজনের যোগফল ০ হলে null */
    private function split(float $total, array $weights): ?array
    {
        $sum = array_sum($weights);

        if ($sum <= 0) {
            return null;
        }

        $parts = [];

        foreach ($weights as $id => $weight) {
            if ($weight > 0) {
                $parts[$id] = $total * $weight / $sum;
            }
        }

        return $parts;
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
