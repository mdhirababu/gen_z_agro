<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function index(Farm $farm, ReportService $reports)
    {
        Gate::authorize('manageFinance', $farm);

        $period = ReportPeriod::make('this_month');

        return view('reports.index', [
            'farm' => $farm,
            'period' => $period,
            'cash' => $reports->cashFlow($farm, $period),
            'pl' => $reports->profitLoss($farm, $period),
            'cowReportAllowed' => $this->cowReportAllowed($farm),
        ]);
    }

    public function cashFlow(Request $request, Farm $farm, ReportService $reports)
    {
        Gate::authorize('manageFinance', $farm);

        $period = ReportPeriod::fromRequest($request);

        return view('reports.cash-flow', [
            'farm' => $farm,
            'period' => $period,
            'report' => $reports->cashFlow($farm, $period),
        ]);
    }

    public function profitLoss(Request $request, Farm $farm, ReportService $reports)
    {
        Gate::authorize('manageFinance', $farm);

        $period = ReportPeriod::fromRequest($request);

        return view('reports.profit-loss', [
            'farm' => $farm,
            'period' => $period,
            'report' => $reports->profitLoss($farm, $period),
        ]);
    }

    public function cows(Request $request, Farm $farm, ReportService $reports)
    {
        Gate::authorize('manageFinance', $farm);

        if (! $this->cowReportAllowed($farm)) {
            return view('reports.locked', ['farm' => $farm]);
        }

        $period = ReportPeriod::fromRequest($request);
        $report = $reports->cowProfitability($farm, $period);

        $status = (string) $request->query('status', 'all');
        $status = in_array($status, ['all', 'active', 'sold', 'gone'], true) ? $status : 'all';

        $sort = (string) $request->query('sort', 'result_desc');
        $sort = in_array($sort, ['result_desc', 'result_asc', 'tag'], true) ? $sort : 'result_desc';

        $rows = collect($report['rows'])->filter(fn($r) => match ($status) {
            'active' => $r['cow']->status === 'active',
            'sold' => $r['cow']->status === 'sold',
            'gone' => in_array($r['cow']->status, ['dead', 'transferred'], true),
            default => true,
        });

        $rows = match ($sort) {
            'result_asc' => $rows->sortBy(fn($r) => $r['result']),
            'tag' => $rows->sortBy(fn($r) => $r['cow']->tag_number, SORT_NATURAL),
            default => $rows->sortByDesc(fn($r) => $r['result']),
        };

        return view('reports.cows', [
            'farm' => $farm,
            'period' => $period,
            'report' => $report,
            'rows' => $rows->values()->all(),
            'status' => $status,
            'sort' => $sort,
        ]);
    }

    /** প্ল্যানে গরুভিত্তিক রিপোর্টের সুবিধা চালু আছে কি না */
    private function cowReportAllowed(Farm $farm): bool
    {
        $farm->loadMissing('subscription.plan');
        $plan = $farm->subscription?->plan;

        return (bool) ($plan && $plan->allows('cow_profit_report'));
    }
}
