<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveExpenseRequest;
use App\Models\Expense;
use App\Models\Farm;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function index(Request $request, Farm $farm)
    {
        Gate::authorize('manageFinance', $farm);

        // মাস: Y-m অথবা all
        $month = (string) $request->query('m', now()->format('Y-m'));
        if ($month !== 'all' && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = now()->format('Y-m');
        }

        $category = (string) $request->query('category', '');
        $cowId = (string) $request->query('cow', '');
        $pay = (string) $request->query('pay', '');
        $state = $request->query('state') === 'void' ? 'void' : 'active';

        $base = $farm->expenses()
            ->where('status', $state)
            ->when($month !== 'all', function ($q) use ($month) {
                $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
                $q->whereBetween('expense_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
            })
            ->when(array_key_exists($category, config('farm.expense_categories')), fn($q) => $q->where('category', $category))
            ->when(ctype_digit($cowId) && $cowId !== '', fn($q) => $q->where('cow_id', (int) $cowId))
            ->when(array_key_exists($pay, config('farm.payment_statuses')), fn($q) => $q->where('payment_status', $pay));

        $totals = (clone $base)
            ->selectRaw('coalesce(sum(total_amount), 0) as total, coalesce(sum(paid_amount), 0) as paid, count(*) as cnt')
            ->first();

        $expenses = (clone $base)
            ->with('cow')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // মাসের তালিকা: গত ১২ মাস
        $months = collect(range(0, 11))->map(fn($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'));
        if ($month !== 'all' && ! $months->contains($month)) {
            $months->push($month);
        }

        return view('expenses.index', [
            'farm' => $farm,
            'expenses' => $expenses,
            'totals' => $totals,
            'month' => $month,
            'months' => $months,
            'cows' => $farm->cows()->orderBy('tag_number')->get(['id', 'tag_number', 'name']),
            'filters' => compact('category', 'cowId', 'pay', 'state'),
        ]);
    }

    public function create(Farm $farm)
    {
        Gate::authorize('manageFinance', $farm);

        return view('expenses.create', ['farm' => $farm, 'cows' => $this->cows($farm)]);
    }

    public function store(SaveExpenseRequest $request, Farm $farm, LedgerService $ledger)
    {
        $expense = $ledger->createExpense(
            $farm,
            $request->payload(),
            (float) $request->total(),
            $request->payment(),
            $request->user()
        );

        return redirect()
            ->route('farms.expenses.show', [$farm, $expense])
            ->with('success', 'খরচ নথিভুক্ত হয়েছে।');
    }

    public function show(Farm $farm, Expense $expense)
    {
        Gate::authorize('manageFinance', $farm);

        $expense->load('cow', 'creator', 'voider');
        $payments = $expense->payments()->with('voider')->orderByDesc('paid_at')->orderByDesc('id')->get();

        return view('expenses.show', compact('farm', 'expense', 'payments'));
    }

    public function edit(Farm $farm, Expense $expense)
    {
        Gate::authorize('manageFinance', $farm);

        if ($expense->isVoid()) {
            return redirect()->route('farms.expenses.show', [$farm, $expense])
                ->with('error', 'বাতিল করা খরচ বদলানো যায় না।');
        }

        return view('expenses.edit', ['farm' => $farm, 'expense' => $expense, 'cows' => $this->cows($farm)]);
    }

    public function update(SaveExpenseRequest $request, Farm $farm, Expense $expense)
    {
        if ($expense->isVoid()) {
            return redirect()->route('farms.expenses.show', [$farm, $expense])
                ->with('error', 'বাতিল করা খরচ বদলানো যায় না।');
        }

        DB::transaction(function () use ($request, $expense) {
            $locked = Expense::withoutGlobalScope('farm')->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            $locked->fill($request->payload());

            // টাকা না দেওয়া থাকলেই অঙ্ক বদলানো যায়
            if ($request->total() !== null && (float) $locked->paid_amount === 0.0) {
                $locked->total_amount = $request->total();
            }

            $locked->save();
        });

        return redirect()
            ->route('farms.expenses.show', [$farm, $expense])
            ->with('success', 'খরচের তথ্য আপডেট হয়েছে।');
    }

    public function void(Request $request, Farm $farm, Expense $expense, LedgerService $ledger)
    {
        Gate::authorize('manageFinance', $farm);

        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:3', 'max:255']],
            [],
            ['reason' => 'বাতিলের কারণ']
        );

        $done = $ledger->void($expense, $request->user(), $data['reason']);

        return redirect()
            ->route('farms.expenses.show', [$farm, $expense])
            ->with($done ? 'success' : 'error', $done ? 'খরচটি বাতিল করা হয়েছে।' : 'খরচটি আগেই বাতিল করা হয়েছে।');
    }

    private function cows(Farm $farm)
    {
        return $farm->cows()->orderBy('tag_number')->get(['id', 'tag_number', 'name', 'status']);
    }
}
