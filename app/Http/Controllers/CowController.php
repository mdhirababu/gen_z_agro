<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCowRequest;
use App\Models\Cow;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CowController extends Controller
{
    public function index(Farm $farm)
    {
        Gate::authorize('viewAny', [Cow::class, $farm]);

        return view('cows.index', compact('farm'));
    }

    public function create(Request $request, Farm $farm)
    {
        Gate::authorize('create', [Cow::class, $farm]);

        // মায়ের পেজ থেকে "বাছুর জন্ম" চাপলে মা আগে থেকে বসে থাকবে
        $mother = $request->filled('mother')
            ? $farm->cows()->where('sex', 'female')->find($request->query('mother'))
            : null;

        $prefill = $mother
            ? ['mother_id' => $mother->id, 'acquisition_type' => 'born', 'category' => 'calf']
            : [];

        return view('cows.create', [
            'farm' => $farm,
            'cow' => null,
            'mother' => $mother,
            'prefill' => $prefill,
            'mothers' => $this->mothers($farm),
            'usage' => $this->usage($farm),
        ]);
    }

    public function store(SaveCowRequest $request, Farm $farm)
    {
        $data = $request->payload();

        $cow = DB::transaction(function () use ($request, $farm, $data) {
            // একসঙ্গে দুজন যোগ করলেও সীমা পার না হয়: খামারের সারি লক করে গোনা
            $locked = Farm::whereKey($farm->id)->lockForUpdate()->firstOrFail();
            $this->ensureCapacity($locked);

            $cow = new Cow($data);
            $cow->forceFill([
                'farm_id' => $farm->id,
                'created_by' => $request->user()->id,
                'source' => Cow::SOURCE_MANUAL,
            ]);

            if ($request->hasFile('photo')) {
                $cow->photo_path = app(\App\Services\ImageSanitizer::class)->store($request->file('photo'), "cows/{$farm->id}");
            }

            $cow->save();

            return $cow;
        });

        return redirect()
            ->route('farms.cows.show', [$farm, $cow])
            ->with('success', 'গরুর তথ্য সংরক্ষণ হয়েছে।');
    }

    public function show(Farm $farm, Cow $cow)
    {
        Gate::authorize('view', $cow);

        $cow->load('mother', 'creator');
        $calves = $cow->calves()->orderByDesc('birth_date')->get();
        $weights = $cow->weights()->orderBy('measured_at')->orderBy('id')->get();

        // টেবিলের জন্য: আগের ওজনের সাথে পার্থক্যসহ, নতুন আগে
        $rows = [];
        $prev = null;
        foreach ($weights as $record) {
            $current = (float) $record->weight_kg;
            $rows[] = ['record' => $record, 'diff' => $prev === null ? null : round($current - $prev, 1)];
            $prev = $current;
        }
        $rows = array_reverse($rows);

        $stats = null;
        if ($weights->isNotEmpty()) {
            $first = $weights->first();
            $last = $weights->last();
            $gain = round((float) $last->weight_kg - (float) $first->weight_kg, 1);
            $days = max(1, (int) abs($first->measured_at->diffInDays($last->measured_at)));

            $stats = [
                'latest' => (float) $last->weight_kg,
                'count' => $weights->count(),
                'gain' => $gain,
                'days' => $days,
                'daily' => round($gain / $days, 2),
            ];
        }

        // মালিক ও ম্যানেজারের জন্য: গরুর বিনিয়োগ ও বিক্রির ফলাফল
        $finance = null;
        if (auth()->user()->can('manageFinance', $farm)) {
            $costs = $farm->expenses()
                ->where('cow_id', $cow->id)
                ->where('status', 'active')
                ->selectRaw('category, sum(total_amount) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get();

            $expenseTotal = (float) $costs->sum('total');
            $invested = $cow->baseValue() + $expenseTotal;

            $cowSale = $farm->sales()
                ->where('sale_type', 'cow')
                ->where('cow_id', $cow->id)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            $finance = [
                'costs' => $costs,
                'expenseTotal' => $expenseTotal,
                'invested' => $invested,
                'sale' => $cowSale,
                'result' => $cowSale ? round((float) $cowSale->total_amount - $invested, 2) : null,
            ];
        }

        $healthEvents = $cow->healthEvents()->orderByDesc('event_date')->orderByDesc('id')->limit(30)->get();
        $withdrawalUntil = $healthEvents->filter(fn($e) => $e->withdrawalActive())->max('milk_withdrawal_until');
        $breedingEvents = $cow->breedingEvents()->orderByDesc('service_date')->orderByDesc('id')->limit(20)->get();
        $activePregnancy = $breedingEvents->firstWhere('status', 'pregnant');

        return view('cows.show', compact('farm', 'cow', 'calves', 'weights', 'rows', 'stats', 'finance', 'healthEvents', 'withdrawalUntil', 'breedingEvents', 'activePregnancy'));
    }

    public function edit(Farm $farm, Cow $cow)
    {
        Gate::authorize('update', $cow);

        return view('cows.edit', [
            'farm' => $farm,
            'cow' => $cow,
            'mother' => null,
            'prefill' => [],
            'mothers' => $this->mothers($farm)->where('id', '!=', $cow->id)->values(),
        ]);
    }

    public function update(SaveCowRequest $request, Farm $farm, Cow $cow)
    {
        $data = $request->payload();

        DB::transaction(function () use ($request, $farm, $cow, $data) {
            $locked = Farm::whereKey($farm->id)->lockForUpdate()->firstOrFail();

            // মৃত বা সরানো গরু আবার সক্রিয় করলেও প্ল্যানের সীমা দেখা হবে
            $becomesActive = ($data['status'] ?? $cow->status) === Cow::STATUS_ACTIVE
                && $cow->status !== Cow::STATUS_ACTIVE;
            if ($becomesActive) {
                $this->ensureCapacity($locked);
            }

            $cow->fill($data);

            if ($request->boolean('remove_photo') && $cow->photo_path) {
                Storage::disk('public')->delete($cow->photo_path);
                $cow->photo_path = null;
            }

            if ($request->hasFile('photo')) {
                $newPath = app(\App\Services\ImageSanitizer::class)->store($request->file('photo'), "cows/{$farm->id}");

                if ($cow->photo_path) {
                    Storage::disk('public')->delete($cow->photo_path);
                }

                $cow->photo_path = $newPath;
            }

            $cow->save();
        });

        return redirect()
            ->route('farms.cows.show', [$farm, $cow])
            ->with('success', 'গরুর তথ্য আপডেট হয়েছে।');
    }

    public function destroy(Farm $farm, Cow $cow)
    {
        Gate::authorize('delete', $cow);

        $cow->delete(); // soft delete: ইতিহাস থেকে যায়

        return redirect()
            ->route('farms.cows.index', $farm)
            ->with('success', 'গরুটি তালিকা থেকে সরানো হয়েছে।');
    }

    private function mothers(Farm $farm)
    {
        return $farm->cows()->where('sex', 'female')->orderBy('tag_number')->get(['id', 'tag_number', 'name']);
    }

    private function usage(Farm $farm): array
    {
        $farm->loadMissing('subscription.plan');

        return [
            'active' => $farm->cows()->where('status', Cow::STATUS_ACTIVE)->count(),
            'max' => $farm->subscription ? $farm->subscription->plan->max_cows : null,
            'full' => ! $farm->canAddCows(),
        ];
    }

    private function ensureCapacity(Farm $farm): void
    {
        if (! $farm->canAddCows()) {
            throw ValidationException::withMessages([
                'limit' => 'আপনার প্ল্যানের গরুর সীমা শেষ। প্ল্যান আপগ্রেড করুন অথবা কোনো গরু সরিয়ে দিন।',
            ]);
        }
    }
}
