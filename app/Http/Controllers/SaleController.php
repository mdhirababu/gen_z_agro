<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersByMonth;
use App\Http\Requests\SaveSaleRequest;
use App\Models\Farm;
use App\Models\Sale;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    use FiltersByMonth;

    public function index(Request $request, Farm $farm)
    {
        Gate::authorize('manageFinance', $farm);

        $month = $this->monthParam($request);
        $type = (string) $request->query('type', '');
        $customerId = (string) $request->query('customer', '');
        $pay = (string) $request->query('pay', '');
        $state = $request->query('state') === 'void' ? 'void' : 'active';

        $base = $farm->sales()
            ->where('status', $state)
            ->when($month !== 'all', fn($q) => $q->whereBetween('sold_at', $this->monthRange($month)))
            ->when(array_key_exists($type, config('farm.sale_types')), fn($q) => $q->where('sale_type', $type))
            ->when($customerId !== '' && ctype_digit($customerId), fn($q) => $q->where('customer_id', (int) $customerId))
            ->when(array_key_exists($pay, config('farm.payment_statuses')), fn($q) => $q->where('payment_status', $pay));

        $totals = (clone $base)
            ->selectRaw('coalesce(sum(total_amount), 0) as total, coalesce(sum(paid_amount), 0) as paid, count(*) as cnt')
            ->first();

        $sales = (clone $base)
            ->with('customer', 'cow')
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales.index', [
            'farm' => $farm,
            'sales' => $sales,
            'totals' => $totals,
            'month' => $month,
            'months' => $this->monthOptions($month),
            'customers' => $farm->customers()->orderBy('name')->get(['id', 'name']),
            'filters' => compact('type', 'customerId', 'pay', 'state'),
        ]);
    }

    public function create(Request $request, Farm $farm)
    {
        Gate::authorize('manageFinance', $farm);

        $cows = $farm->cows()->where('status', 'active')->orderBy('tag_number')->get();

        // গরু বিক্রির সময় দেখানোর জন্য: প্রতিটি গরুর মোট বিনিয়োগ (ভিত্তিমূল্য + সরাসরি খরচ)
        $expenseByCow = $farm->expenses()
            ->where('status', 'active')
            ->whereNotNull('cow_id')
            ->selectRaw('cow_id, sum(total_amount) as total')
            ->groupBy('cow_id')
            ->pluck('total', 'cow_id');

        $cowInfo = $cows->mapWithKeys(fn($c) => [
            $c->id => ['invested' => round($c->baseValue() + (float) ($expenseByCow[$c->id] ?? 0), 2)],
        ])->all();

        $type = (string) $request->query('type', 'milk');
        if (! array_key_exists($type, config('farm.sale_types'))) {
            $type = 'milk';
        }

        return view('sales.create', [
            'farm' => $farm,
            'type' => $type,
            'cowId' => (string) $request->query('cow', ''),
            'cows' => $cows,
            'cowInfo' => $cowInfo,
            'customers' => $farm->customers()->orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    public function store(SaveSaleRequest $request, Farm $farm, LedgerService $ledger)
    {
        $sale = $ledger->createSale(
            $farm,
            $request->payload(),
            $request->total(),
            $request->payment(),
            $request->user()
        );

        return redirect()
            ->route('farms.sales.show', [$farm, $sale])
            ->with('success', 'বিক্রি নথিভুক্ত হয়েছে।');
    }

    public function show(Farm $farm, Sale $sale)
    {
        Gate::authorize('manageFinance', $farm);

        $sale->load('customer', 'cow', 'creator', 'voider');
        $payments = $sale->payments()->with('voider')->orderByDesc('paid_at')->orderByDesc('id')->get();

        return view('sales.show', compact('farm', 'sale', 'payments'));
    }

    public function edit(Farm $farm, Sale $sale)
    {
        Gate::authorize('manageFinance', $farm);

        if ($sale->isVoid()) {
            return redirect()->route('farms.sales.show', [$farm, $sale])
                ->with('error', 'বাতিল করা বিক্রি বদলানো যায় না।');
        }

        return view('sales.edit', [
            'farm' => $farm,
            'sale' => $sale,
            'customers' => $farm->customers()->orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    /** শুধু তারিখ, ক্রেতা, বিবরণ, নোট। অঙ্ক বদলাতে হলে বাতিল করে নতুন করে যোগ করতে হয়। */
    public function update(Request $request, Farm $farm, Sale $sale)
    {
        Gate::authorize('manageFinance', $farm);

        if ($sale->isVoid()) {
            return redirect()->route('farms.sales.show', [$farm, $sale])
                ->with('error', 'বাতিল করা বিক্রি বদলানো যায় না।');
        }

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where(fn($q) => $q
                ->where('farm_id', $farm->id)->whereNull('deleted_at'))],
            'sold_at' => ['required', 'date', 'before_or_equal:today'],
            'description' => [Rule::requiredIf($sale->sale_type === 'other_income'), 'nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['customer_id' => 'ক্রেতা', 'sold_at' => 'তারিখ', 'description' => 'বিবরণ']);

        if ($sale->dueAmount() > 0 && empty($data['customer_id'])) {
            throw ValidationException::withMessages([
                'customer_id' => 'বকেয়া থাকা বিক্রি থেকে ক্রেতা সরানো যায় না।',
            ]);
        }

        DB::transaction(function () use ($sale, $data) {
            $locked = Sale::withoutGlobalScope('farm')->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $locked->fill($data)->save();

            // গরু বিক্রির তারিখ বদলালে গরুর বিদায়ের তারিখও বদলায়
            if ($locked->sale_type === 'cow' && $locked->cow_id) {
                \App\Models\Cow::withoutGlobalScope('farm')->withTrashed()
                    ->whereKey($locked->cow_id)->update(['exit_date' => $data['sold_at']]);
            }
        });

        return redirect()
            ->route('farms.sales.show', [$farm, $sale])
            ->with('success', 'বিক্রির তথ্য আপডেট হয়েছে।');
    }

    public function void(Request $request, Farm $farm, Sale $sale, LedgerService $ledger)
    {
        Gate::authorize('manageFinance', $farm);

        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:3', 'max:255']],
            [],
            ['reason' => 'বাতিলের কারণ']
        );

        try {
            $done = $ledger->void($sale, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return redirect()
                ->route('farms.sales.show', [$farm, $sale])
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()
            ->route('farms.sales.show', [$farm, $sale])
            ->with($done ? 'success' : 'error', $done ? 'বিক্রিটি বাতিল করা হয়েছে।' : 'বিক্রিটি আগেই বাতিল করা হয়েছে।');
    }
}
