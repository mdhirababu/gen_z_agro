<?php

namespace App\Support;

use App\Models\Farm;

final class ReportDocuments
{
    private const RULES = [
        'feed_records' => 'খাবারের রেকর্ড অনুযায়ী (পরিমাণ × দাম)',
        'cow_days' => 'গরু-দিন অনুযায়ী',
        'equal' => 'সমান ভাগে',
    ];

    /**
     * অংশের গঠন: heading, columns, rows, money (টাকার কলাম), int (পূর্ণসংখ্যার কলাম), total (শেষ সারি মোট কি না)
     */
    private static function document(Farm $farm, ReportPeriod $p, string $title, array $widths, bool $landscape, array $sections, array $notes): array
    {
        return [
            'title' => $title,
            'farm' => $farm->name,
            'period' => $p->label,
            'widths' => $widths,
            'landscape' => $landscape,
            'sections' => $sections,
            'notes' => $notes,
        ];
    }

    public static function cashFlow(Farm $farm, ReportPeriod $p, array $r): array
    {
        $series = $r['series'];
        $daily = $series['granularity'] === 'day';

        $byTime = [];
        foreach ($series['labels'] as $i => $label) {
            $in = (float) $series['in'][$i];
            $out = (float) $series['out'][$i];

            if ($in > 0 || $out > 0) {
                $byTime[] = [$label, $in, $out, round($in - $out, 2)];
            }
        }
        $byTime[] = ['মোট', $r['in_total'], $r['out_total'], $r['net']];

        $inRows = array_map(fn($x) => [$x['label'], $x['total']], $r['in_by_type']);
        $inRows[] = ['মোট নগদ আয়', $r['in_total']];

        $outRows = array_map(fn($x) => [$x['label'], $x['total']], $r['out_by_category']);
        $outRows[] = ['মোট নগদ ব্যয়', $r['out_total']];

        return self::document($farm, $p, 'নগদ প্রবাহ', [34, 18, 18, 18], false, [
            [
                'heading' => 'সারসংক্ষেপ',
                'columns' => ['বিষয়', 'টাকা'],
                'rows' => [['নগদ আয়', $r['in_total']], ['নগদ ব্যয়', $r['out_total']], ['নিট নগদ', $r['net']]],
                'money' => [1],
                'total' => true,
            ],
            [
                'heading' => 'কোন আয় থেকে কত এলো',
                'columns' => ['আয়ের ধরন', 'টাকা'],
                'rows' => $inRows,
                'money' => [1],
                'total' => true,
            ],
            [
                'heading' => 'কোন খাতে কত গেল',
                'columns' => ['খাত', 'টাকা'],
                'rows' => $outRows,
                'money' => [1],
                'total' => true,
            ],
            [
                'heading' => $daily ? 'দিনভিত্তিক আয় ও ব্যয়' : 'মাসভিত্তিক আয় ও ব্যয়',
                'columns' => [$daily ? 'তারিখ' : 'মাস', 'নগদ আয়', 'নগদ ব্যয়', 'নিট'],
                'rows' => $byTime,
                'money' => [1, 2, 3],
                'total' => true,
            ],
        ], [
            'শুধু আসলে টাকা পাওয়া বা দেওয়ার রেকর্ড ধরা হয়েছে। বকেয়া বিক্রি বা বকেয়া খরচ এতে নেই, বাতিল করা পেমেন্টও ধরা হয়নি।',
            'গরু কেনার টাকা কেনার তারিখে পুরোটা বেরিয়ে গেছে ধরা হয়েছে। আগে থেকে ছিল বা খামারে জন্মানো গরুর জন্য কোনো নগদ খরচ ধরা হয় না।',
        ]);
    }

    public static function profitLoss(Farm $farm, ReportPeriod $p, array $r): array
    {
        $incomeRows = [];
        foreach (config('farm.sale_types') as $key => $meta) {
            $incomeRows[] = [$meta['label'], $r['revenue'][$key] ?? 0.0];
        }
        $incomeRows[] = ['মোট আয় (গরু বিক্রিসহ)', round($r['operating_income'] + $r['revenue']['cow'], 2)];

        $expenseRows = array_map(fn($e) => [$e['label'], $e['total']], $r['expenses']);
        $expenseRows[] = ['মোট ব্যয়', $r['operating_expense']];

        $sections = [
            [
                'heading' => 'নিট ফল কীভাবে এলো',
                'columns' => ['বিষয়', 'টাকা'],
                'rows' => [
                    ['পরিচালন আয় (গরু বিক্রি বাদে)', $r['operating_income']],
                    ['পরিচালন ব্যয়', $r['operating_expense']],
                    ['পরিচালন ফল', $r['operating_result']],
                    ['গরু বিক্রির ফল', $r['cow_result']],
                    ['গরু বাদ পড়ায় ক্ষতি', $r['write_offs']['total']],
                    ['আনুমানিক নিট ফল', $r['net']],
                ],
                'money' => [1],
                'total' => true,
            ],
            [
                'heading' => 'আয়',
                'columns' => ['আয়ের ধরন', 'টাকা'],
                'rows' => $incomeRows,
                'money' => [1],
                'total' => true,
            ],
            [
                'heading' => 'ব্যয়',
                'columns' => ['খরচের ধরন', 'টাকা'],
                'rows' => $expenseRows,
                'money' => [1],
                'total' => true,
            ],
        ];

        if (count($r['cow_lines']) > 0) {
            $rows = array_map(fn($l) => [$l['tag'], $l['sold_at']->format('d/m/Y'), $l['price'], $l['base'], $l['result']], $r['cow_lines']);
            $rows[] = ['মোট', '', $r['cow_sales_total'], $r['cow_base_total'], $r['cow_result']];

            $sections[] = [
                'heading' => 'গরু বিক্রির ফল',
                'columns' => ['গরু', 'তারিখ', 'বিক্রির টাকা', 'ভিত্তিমূল্য', 'ফল'],
                'rows' => $rows,
                'money' => [2, 3, 4],
                'total' => true,
            ];
        }

        if (count($r['write_offs']['lines']) > 0) {
            $rows = array_map(fn($l) => [
                $l['tag'],
                $l['status'] === 'dead' ? 'মারা গেছে' : 'অন্যত্র সরানো হয়েছে',
                $l['exit_date']->format('d/m/Y'),
                $l['base'],
            ], $r['write_offs']['lines']);
            $rows[] = ['মোট', '', '', $r['write_offs']['total']];

            $sections[] = [
                'heading' => 'গরু বাদ পড়ায় ক্ষতি',
                'columns' => ['গরু', 'অবস্থা', 'তারিখ', 'ভিত্তিমূল্য'],
                'rows' => $rows,
                'money' => [3],
                'total' => true,
            ];
        }

        $sections[] = [
            'heading' => 'দুধ: উৎপাদন বনাম বিক্রি',
            'columns' => ['বিষয়', 'মান'],
            'rows' => [
                ['উৎপাদন (লিটার)', $r['milk']['produced']],
                ['বিক্রি (লিটার)', $r['milk']['sold']],
                ['দুধ বিক্রির আয় (টাকা)', $r['milk']['revenue']],
                ['গড় দর (টাকা প্রতি লিটার)', $r['milk']['avg_price'] ?? '—'],
            ],
        ];

        return self::document($farm, $p, 'আনুমানিক লাভ-ক্ষতি', [44, 18, 18, 18, 18], false, $sections, [
            'এটা আনুমানিক হিসাব, আইনি হিসাবরক্ষণ নয়। বকেয়া বিক্রি ও বকেয়া খরচও ধরা হয়েছে, টাকা আসুক বা না আসুক।',
            'অবিক্রীত গরুর বর্তমান বাজারদর ধরা নেই। গরু কেনার টাকা খরচ নয়, বিক্রি হলে ভিত্তিমূল্য হিসেবে বাদ যায়।',
            'বাতিল করা বিক্রি বা খরচ ধরা হয়নি।',
        ]);
    }

    public static function cows(Farm $farm, ReportPeriod $p, array $report, array $rows, string $status = 'all'): array
    {
        $cats = config('farm.categories');
        $statusLabels = ['active' => 'সক্রিয়', 'sold' => 'বিক্রিত', 'dead' => 'মৃত', 'transferred' => 'সরানো'];

        $sum = ['liters' => 0.0, 'income' => 0.0, 'direct' => 0.0, 'joint' => 0.0, 'base' => 0.0, 'result' => 0.0];
        $table = [];

        foreach ($rows as $row) {
            $cow = $row['cow'];

            $table[] = [
                $cow->name ? $cow->tag_number . ' (' . $cow->name . ')' : (string) $cow->tag_number,
                $cats[$cow->category]['label'] ?? $cow->category,
                $statusLabels[$cow->status] ?? $cow->status,
                $row['days'],
                $row['liters'],
                $row['income'],
                $row['cost_direct'],
                $row['joint'],
                $row['cost_base'],
                $row['result'],
            ];

            $sum['liters'] += $row['liters'];
            $sum['income'] += $row['income'];
            $sum['direct'] += $row['cost_direct'];
            $sum['joint'] += $row['joint'];
            $sum['base'] += $row['cost_base'];
            $sum['result'] += $row['result'];
        }

        $table[] = ['মোট', '', '', '', round($sum['liters'], 2), round($sum['income'], 2), round($sum['direct'], 2), round($sum['joint'], 2), round($sum['base'], 2), round($sum['result'], 2)];

        $un = $report['unallocated'];
        $recon = [['সব গরুর ফলের যোগফল (ফিল্টার ছাড়া)', $report['totals']['result']]];
        foreach ($un['income_lines'] as $line) {
            $recon[] = ['+ ' . $line['label'], $line['amount']];
        }
        foreach ($un['expense_lines'] as $line) {
            $recon[] = ['− ' . $line['label'], $line['amount']];
        }
        $recon[] = ['আনুমানিক নিট ফল', $report['check_net']];

        $notes = [
            'যৌথ খাবারের খরচ: ' . (self::RULES[$report['rules']['feed']] ?? $report['rules']['feed'])
                . ($report['feed_fallback'] ? ' (খাবারের রেকর্ড বা দাম নেই বলে গরু-দিনে ভাগ হয়েছে)' : ''),
            'অন্যান্য যৌথ খরচ (শ্রমিক, বিদ্যুৎ ইত্যাদি): ' . (self::RULES[$report['rules']['other']] ?? $report['rules']['other']),
            'দুধের আয় সময়কালে কোন গাভি কত লিটার দুধ দিয়েছে সেই অনুপাতে ভাগ হয়েছে। এটা আনুমানিক হিসাব।',
            'গরুর ভিত্তিমূল্য খরচ হিসেবে আসে শুধু গরু বিক্রি হলে, মারা গেলে বা অন্যত্র সরালে।',
        ];

        if ($status !== 'all') {
            $notes[] = 'তালিকায় শুধু ফিল্টার করা গরু দেখানো হয়েছে, মেলানো অংশে সব গরু ধরা।';
        }

        return self::document($farm, $p, 'গরুভিত্তিক লাভ-ক্ষতি', [36, 14, 12, 8, 12, 16, 16, 18, 16, 16], true, [
            [
                'heading' => 'গরুর তালিকা',
                'columns' => ['গরু', 'ধরন', 'অবস্থা', 'দিন', 'দুধ (লি.)', 'আয়', 'সরাসরি খরচ', 'যৌথ খরচের ভাগ', 'ভিত্তিমূল্য', 'ফল'],
                'rows' => $table,
                'money' => [5, 6, 7, 8, 9],
                'int' => [3],
                'total' => true,
            ],
            [
                'heading' => 'খামারের নিট ফলের সঙ্গে মেলানো',
                'columns' => ['বিষয়', 'টাকা'],
                'rows' => $recon,
                'money' => [1],
                'total' => true,
            ],
        ], $notes);
    }
}
