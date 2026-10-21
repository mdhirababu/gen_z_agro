<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FarmController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $farms = $user->is_super_admin
            ? Farm::with('owner', 'subscription.plan')->latest()->get()
            : $user->farms()->with('subscription.plan')->latest('farms.created_at')->get();

        return view('farms.index', compact('farms'));
    }

    public function create()
    {
        return view('farms.create');
    }

    public function store(Request $request, SubscriptionService $subscriptions)
    {
        $data = $this->validated($request);

        $farm = DB::transaction(function () use ($request, $data, $subscriptions) {
            $farm = Farm::create($data + [
                'owner_user_id' => $request->user()->id,
            ]);

            $farm->users()->attach($request->user()->id, [
                'role' => Farm::ROLE_OWNER,
            ]);

            $subscriptions->startTrial($farm); // ১৪ দিনের ট্রায়াল শুরু

            return $farm;
        });

        return redirect()
            ->route('farms.dashboard', $farm)
            ->with('success', 'খামার তৈরি হয়েছে। ট্রায়াল শুরু হলো।');
    }

    public function show(Farm $farm)
    {
        Gate::authorize('view', $farm);

        return redirect()->route('farms.dashboard', $farm);
    }

    public function edit(Farm $farm)
    {
        Gate::authorize('update', $farm);

        return view('farms.edit', compact('farm'));
    }

    public function update(Request $request, Farm $farm)
    {
        Gate::authorize('update', $farm);

        $farm->update($this->validated($request));

        return redirect()
            ->route('farms.dashboard', $farm)
            ->with('success', 'খামারের তথ্য আপডেট হয়েছে।');
    }

    public function destroy(Farm $farm)
    {
        Gate::authorize('delete', $farm);

        $farm->delete(); // soft delete

        return redirect()
            ->route('farms.index')
            ->with('success', 'খামার মুছে ফেলা হয়েছে।');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'district' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
