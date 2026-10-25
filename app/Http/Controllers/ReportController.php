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
}
