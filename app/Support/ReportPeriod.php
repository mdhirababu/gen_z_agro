<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class ReportPeriod
{
    public function __construct(
        public string $key,
        public Carbon $from,
        public Carbon $to,
        public string $label,
        public ?string $notice = null,
    ) {}

    public static function presets(): array
    {
        return [
            'today' => 'আজ',
            'this_week' => 'এই সপ্তাহ',
            'this_month' => 'এই মাস',
            'last_month' => 'গত মাস',
            'this_year' => 'এই বছর',
            'fiscal_year' => 'অর্থবছর',
            'custom' => 'নিজের মতো',
        ];
    }

    public static function fromRequest(Request $request): self
    {
        $from = $request->query('from');
        $to = $request->query('to');

        return self::make(
            (string) $request->query('r', 'this_month'),
            is_string($from) ? $from : null,
            is_string($to) ? $to : null,
        );
    }

    /** ভুল পরিসর এলে নিরাপদে "এই মাস"-এ ফিরে যায়, সঙ্গে একটা নোটিশ থাকে */
    public static function make(string $key = 'this_month', ?string $from = null, ?string $to = null): self
    {
        $today = now()->startOfDay();
        $notice = null;
        $customStart = null;
        $customEnd = null;

        if (! array_key_exists($key, self::presets())) {
            $key = 'this_month';
        }

        if ($key === 'custom') {
            try {
                $customStart = Carbon::createFromFormat('Y-m-d', (string) $from)->startOfDay();
                $customEnd = Carbon::createFromFormat('Y-m-d', (string) $to)->startOfDay();

                if ($customStart->gt($customEnd) || $customEnd->gt($today) || $customStart->diffInDays($customEnd) > 800) {
                    throw new \InvalidArgumentException('range');
                }
            } catch (\Throwable) {
                $key = 'this_month';
                $notice = 'তারিখের পরিসর ঠিক নয় (শুরু শেষের আগে হতে হবে, আজকের পরে নয়, সর্বোচ্চ প্রায় ২ বছর), তাই এই মাস দেখানো হচ্ছে।';
            }
        }

        $lastMonth = $today->copy()->subMonthNoOverflow();

        [$start, $end] = match ($key) {
            'today' => [$today->copy(), $today->copy()],
            'this_week' => [$today->copy()->startOfWeek(Carbon::SATURDAY), $today->copy()],
            'last_month' => [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()->startOfDay()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()],
            'fiscal_year' => [self::fiscalStart($today), $today->copy()],
            'custom' => [$customStart, $customEnd],
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };

        $range = $start->format('d/m/Y') . ' থেকে ' . $end->format('d/m/Y');
        $label = $key === 'custom' ? $range : self::presets()[$key] . ' (' . $range . ')';

        return new self($key, $start, $end, $label, $notice);
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    private static function fiscalStart(Carbon $today): Carbon
    {
        $year = $today->month >= 7 ? $today->year : $today->year - 1;

        return Carbon::create($year, 7, 1)->startOfDay();
    }
}
