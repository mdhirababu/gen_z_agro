<?php

namespace App\Http\Controllers;

use App\Models\Cow;
use App\Services\HealthService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request, HealthService $health, TaskService $taskService)
    {
        $farm = $request->attributes->get('currentFarm');
        $user = $request->user();
        $canFinance = $user->can('manageFinance', $farm);

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        // ---- গরু ----
        $cowCounts = $farm->cows()
            ->where('status', Cow::STATUS_ACTIVE)
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        // ---- দুধ ----
        $milkToday = (float) $farm->milkRecords()->whereDate('milk_date', $today)->sum('total_liters');

        $byDate = $farm->milkRecords()
            ->whereDate('milk_date', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('milk_date, sum(total_liters) as total')
            ->groupBy('milk_date')
            ->get()
            ->mapWithKeys(fn($r) => [$r->milk_date->toDateString() => (float) $r->total]);

        $milkWeek = collect(range(6, 0))->map(function ($i) use ($byDate) {
            $d = now()->subDays($i);

            return ['label' => $d->format('d/m'), 'liters' => (float) ($byDate[$d->toDateString()] ?? 0)];
        });

        $data = [
            'farm' => $farm,
            'canFinance' => $canFinance,
            'cowCounts' => $cowCounts,
            'cowTotal' => (int) $cowCounts->sum(),
            'milkToday' => $milkToday,
            'milkWeek' => $milkWeek,
            'milkAvg' => round($milkWeek->sum('liters') / 7, 1),
            'monthSales' => 0.0,
            'monthExpenses' => 0.0,
            'receivable' => 0.0,
            'payable' => 0.0,
            'expenseSegments' => [],
            'chart' => null,
            'topDues' => collect(),
        ];

        // ---- আসন্ন কাজ (টিকা, গর্ভ পরীক্ষা, প্রসব) ----
        $upcoming = $taskService->upcoming($farm, 30);
        $data['tasks'] = $upcoming->take(5);
        $data['taskCount'] = $upcoming->count();
        $data['overdueCount'] = $upcoming->filter(fn($t) => $t->isOverdue())->count();
        $data['sickCount'] = (int) $farm->healthEvents()
            ->where('kind', 'treatment')
            ->where('outcome', 'ongoing')
            ->distinct()
            ->count('cow_id');
        $data['pregnantCount'] = (int) $farm->breedingEvents()
            ->where('status', 'pregnant')
            ->whereHas('cow', fn($q) => $q->where('status', 'active')->whereNull('deleted_at'))
            ->count();

        // ---- টাকা: শুধু মালিক ও ম্যানেজার ----
        if ($canFinance) {
            $data['monthSales'] = (float) $farm->sales()->where('status', 'active')
                ->whereDate('sold_at', '>=', $monthStart)->sum('total_amount');

            $data['monthExpenses'] = (float) $farm->expenses()->where('status', 'active')
                ->whereDate('expense_date', '>=', $monthStart)->sum('total_amount');

            // মোট পাওনা (ক্রেতাদের কাছে) ও দেনা (বকেয়া খরচ)
            $data['receivable'] = (float) $farm->sales()->where('status', 'active')
                ->selectRaw('coalesce(sum(total_amount - paid_amount), 0) as due')->value('due');

            $data['payable'] = (float) $farm->expenses()->where('status', 'active')
                ->selectRaw('coalesce(sum(total_amount - paid_amount), 0) as due')->value('due');

            // এই মাসের খরচের ভাগ
            $byCategory = $farm->expenses()->where('status', 'active')
                ->whereDate('expense_date', '>=', $monthStart)
                ->selectRaw('category, sum(total_amount) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get();

            foreach ($byCategory as $row) {
                $meta = config('farm.expense_categories')[$row->category] ?? null;
                $data['expenseSegments'][] = [
                    $meta['label'] ?? $row->category,
                    (int) round((float) $row->total),
                    $meta['color'] ?? '#8a9c93',
                ];
            }

            // গত ১২ মাসের বিক্রি ও খরচ (মাসভিত্তিক)
            $from = now()->startOfMonth()->subMonths(11);

            $bucket = function ($relation, string $column) use ($from) {
                return $relation
                    ->where('status', 'active')
                    ->where($column, '>=', $from->toDateString())
                    ->selectRaw($column . ' as d, sum(total_amount) as total')
                    ->groupBy($column)
                    ->get()
                    ->groupBy(fn($r) => Carbon::parse($r->d)->format('Y-m'))
                    ->map(fn($rows) => (float) $rows->sum('total'));
            };

            $salesByMonth = $bucket($farm->sales(), 'sold_at');
            $expensesByMonth = $bucket($farm->expenses(), 'expense_date');

            $names = ['জানু', 'ফেব্রু', 'মার্চ', 'এপ্রি', 'মে', 'জুন', 'জুলা', 'আগ', 'সেপ্টে', 'অক্টো', 'নভে', 'ডিসে'];
            $months = collect(range(11, 0))->map(fn($i) => now()->startOfMonth()->subMonths($i));

            $data['chart'] = [
                'labels' => $months->map(fn($m) => $names[$m->month - 1])->all(),
                'sales' => $months->map(fn($m) => (float) ($salesByMonth[$m->format('Y-m')] ?? 0))->all(),
                'expenses' => $months->map(fn($m) => (float) ($expensesByMonth[$m->format('Y-m')] ?? 0))->all(),
            ];

            // সবচেয়ে বেশি বকেয়া যে তিন ক্রেতার
            $data['topDues'] = $farm->customers()
                ->withSum(['sales as total_sum' => fn($q) => $q->where('status', 'active')], 'total_amount')
                ->withSum(['sales as paid_sum' => fn($q) => $q->where('status', 'active')], 'paid_amount')
                ->get()
                ->map(fn($c) => ['customer' => $c, 'due' => round((float) $c->total_sum - (float) $c->paid_sum, 2)])
                ->filter(fn($x) => $x['due'] > 0)
                ->sortByDesc('due')
                ->take(3)
                ->values();
        }

        // ---- শুরু করুন তালিকা ----
        $checklist = collect([
            [
                'label' => 'খামারের ফোন ও ঠিকানা দিন',
                'done' => filled($farm->phone) && filled($farm->address),
                'url' => route('farms.edit', $farm),
                'show' => $user->can('update', $farm)
            ],
            [
                'label' => 'প্রথম গরু যোগ করুন',
                'done' => $farm->cows()->exists(),
                'url' => route('farms.cows.create', $farm),
                'show' => true
            ],
            [
                'label' => 'দুধের হিসাব শুরু করুন',
                'done' => $farm->milkRecords()->exists(),
                'url' => route('farms.milk.entry', $farm),
                'show' => true
            ],
            [
                'label' => 'প্রথম খরচ নথিভুক্ত করুন',
                'done' => $farm->expenses()->exists(),
                'url' => route('farms.expenses.create', $farm),
                'show' => $canFinance
            ],
            [
                'label' => 'সদস্য যোগ করুন',
                'done' => $farm->users()->count() > 1,
                'url' => route('farms.members.index', $farm),
                'show' => $user->can('manageMembers', $farm)
            ],
        ])->where('show', true)->values();

        $data['checklist'] = $checklist;
        $data['checklistDone'] = $checklist->every(fn($item) => $item['done']);
        // ---- শুরুর গাইড (ধাপ, অগ্রগতি, লুকানো) ----
        $data['onboarding'] = app(\App\Services\OnboardingService::class)->for($farm, $user);

        return view('dashboard', $data);
    }
}
