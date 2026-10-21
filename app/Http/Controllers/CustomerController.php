<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Farm $farm)
    {
        Gate::authorize('manageFinance', $farm);

        $customers = $farm->customers()
            ->withCount('sales')
            ->withSum(['sales as total_sum' => fn($q) => $q->where('status', 'active')], 'total_amount')
            ->withSum(['sales as paid_sum' => fn($q) => $q->where('status', 'active')], 'paid_amount')
            ->orderBy('name')
            ->get();

        // বেশি বকেয়া যাঁর, তিনি আগে
        $customers = $customers
            ->sortByDesc(fn($c) => (float) $c->total_sum - (float) $c->paid_sum)
            ->values();

        $totalDue = (float) $customers->sum(fn($c) => (float) $c->total_sum - (float) $c->paid_sum);

        return view('customers.index', compact('farm', 'customers', 'totalDue'));
    }

    public function store(Request $request, Farm $farm)
    {
        Gate::authorize('manageFinance', $farm);

        $data = $request->validate($this->rules($farm), [], $this->names());

        $customer = new Customer($data);
        $customer->forceFill(['farm_id' => $farm->id, 'created_by' => $request->user()->id])->save();

        return back()->with('success', 'ক্রেতা যোগ করা হয়েছে।');
    }

    public function show(Farm $farm, Customer $customer)
    {
        Gate::authorize('manageFinance', $farm);

        $sales = $customer->sales()->with('cow')->orderByDesc('sold_at')->orderByDesc('id')->get();
        $active = $sales->where('status', 'active');

        return view('customers.show', [
            'farm' => $farm,
            'customer' => $customer,
            'sales' => $sales,
            'total' => (float) $active->sum('total_amount'),
            'paid' => (float) $active->sum('paid_amount'),
        ]);
    }

    public function update(Request $request, Farm $farm, Customer $customer)
    {
        Gate::authorize('manageFinance', $farm);

        $data = $request->validateWithBag('edit', $this->rules($farm, $customer), [], $this->names());

        $customer->update($data);

        return back()->with('success', 'ক্রেতার তথ্য আপডেট হয়েছে।');
    }

    public function destroy(Farm $farm, Customer $customer)
    {
        Gate::authorize('manageFinance', $farm);

        // বিক্রির ইতিহাস থাকলে ক্রেতা সরানো যায় না
        if ($customer->sales()->exists()) {
            return back()->with('error', 'এই ক্রেতার বিক্রির হিসাব আছে, তাই সরানো যাবে না।');
        }

        $customer->delete();

        return back()->with('success', 'ক্রেতা সরানো হয়েছে।');
    }

    private function rules(Farm $farm, ?Customer $customer = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('customers', 'name')
                    ->where('farm_id', $farm->id)
                    ->whereNull('deleted_at')
                    ->ignore($customer?->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function names(): array
    {
        return ['name' => 'নাম', 'phone' => 'ফোন', 'address' => 'ঠিকানা'];
    }
}
