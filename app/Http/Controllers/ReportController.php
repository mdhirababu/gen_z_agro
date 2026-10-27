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
        $view = $reports->cowRows($report, $request->query('status'), $request->query('sort'));

        return view('reports.cows', [
            'farm' => $farm,
            'period' => $period,
            'report' => $report,
            'rows' => $view['rows'],
            'status' => $view['status'],
            'sort' => $view['sort'],
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
