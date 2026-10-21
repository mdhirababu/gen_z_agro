<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

trait FiltersByMonth
{
    /** Y-m অথবা all */
    protected function monthParam(Request $request): string
    {
        $m = (string) $request->query('m', now()->format('Y-m'));

        return ($m === 'all' || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) ? $m : now()->format('Y-m');
    }

    protected function monthRange(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    protected function monthOptions(string $month): Collection
    {
        $months = collect(range(0, 11))->map(fn($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'));

        if ($month !== 'all' && ! $months->contains($month)) {
            $months->push($month);
        }

        return $months;
    }
}
