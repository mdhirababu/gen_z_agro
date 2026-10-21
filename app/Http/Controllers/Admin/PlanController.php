<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', [
            'plans' => Plan::withCount('subscriptions')->orderBy('sort_order')->get(),
        ]);
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'max_cows' => ['nullable', 'integer', 'min:1'],     // ফাঁকা = সীমাহীন
            'max_members' => ['nullable', 'integer', 'min:1'],  // ফাঁকা = সীমাহীন
        ]);

        $plan->update($data + [
            'is_active' => $request->boolean('is_active'),
            'features' => [
                'export' => $request->boolean('feature_export'),
                'cow_profit_report' => $request->boolean('feature_cow_profit_report'),
            ],
        ]);

        return redirect()->route('admin.plans.index')->with('success', 'প্ল্যান আপডেট হয়েছে।');
    }
}
