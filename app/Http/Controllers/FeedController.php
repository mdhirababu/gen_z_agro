<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersByMonth;
use App\Models\Farm;
use App\Models\FeedRecord;
use App\Models\FeedType;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeedController extends Controller
{
    use FiltersByMonth;

    public function index(Request $request, Farm $farm)
    {
        Gate::authorize('view', $farm);

        $month = $this->monthParam($request);
        if ($month === 'all') {
            $month = now()->format('Y-m');
        }
        [$start, $end] = $this->monthRange($month);

        $user = $request->user();
        $canFinance = $user->can('manageFinance', $farm);

        $inMonth = fn() => $farm->feedRecords()
            ->whereDate('fed_on', '>=', $start)
            ->whereDate('fed_on', '<=', $end);

        $byType = $inMonth()
            ->selectRaw('feed_type_id, unit, sum(quantity) as total, count(*) as records')
            ->groupBy('feed_type_id', 'unit')
            ->get();

        $types = $farm->feedTypes()->withTrashed()->whereIn('id', $byType->pluck('feed_type_id'))->get()->keyBy('id');

        // আনুমানিক খরচ (পরিমাণ × ধরনের দাম): শুধু মালিক ও ম্যানেজার, শুধু তথ্যের জন্য
        $estimated = null;
        if ($canFinance) {
            $estimated = round((float) $byType->sum(function ($row) use ($types) {
                $price = (float) ($types->get($row->feed_type_id)?->price_per_unit ?? 0);

                return (float) $row->total * $price;
            }), 2);
        }

        return view('feed.index', [
            'farm' => $farm,
            'month' => $month,
            'months' => $this->monthOptions($month),
            'byType' => $byType,
            'types' => $types,
            'estimated' => $estimated,
            'recordCount' => (int) $byType->sum('records'),
            'records' => $inMonth()->with(['feedType', 'cow'])->orderByDesc('fed_on')->orderByDesc('id')->paginate(20)->withQueryString(),
            'activeTypes' => $farm->feedTypes()->where('is_active', true)->orderBy('name')->get(),
            'cows' => $farm->cows()->where('status', 'active')->orderBy('tag_number')->get(['id', 'tag_number', 'name']),
            'canManage' => $user->can('manageFeed', $farm),
            'canFinance' => $canFinance,
        ]);
    }

    public function store(Request $request, Farm $farm)
    {
        Gate::authorize('view', $farm);

        $target = $request->input('target');

        $data = $request->validate([
            'fed_on' => ['required', 'date', 'before_or_equal:today'],
            'feed_type_id' => ['required', 'integer', Rule::exists('feed_types', 'id')->where(fn($q) => $q
                ->where('farm_id', $farm->id)->where('is_active', true)->whereNull('deleted_at'))],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'target' => ['required', Rule::in(array_keys(config('farm.feed_targets')))],
            'category' => ['nullable', Rule::requiredIf($target === 'category'), Rule::in(array_keys(config('farm.categories')))],
            'cow_id' => ['nullable', Rule::requiredIf($target === 'cow'), 'integer', Rule::exists('cows', 'id')->where(fn($q) => $q
                ->where('farm_id', $farm->id)->where('status', 'active')->whereNull('deleted_at'))],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'fed_on.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
            'feed_type_id.exists' => 'খাবারের ধরনটি এই খামারের সক্রিয় ধরন হতে হবে।',
            'cow_id.exists' => 'গরুটি এই খামারের সক্রিয় গরু হতে হবে।',
        ], [
            'fed_on' => 'তারিখ',
            'feed_type_id' => 'খাবারের ধরন',
            'quantity' => 'পরিমাণ',
            'target' => 'কাদের জন্য',
            'category' => 'গরুর ধরন',
            'cow_id' => 'গরু',
        ]);

        // রেকর্ডের সময়ের সক্রিয় গরুর সংখ্যা জমা রাখা হয়
        $active = $farm->cows()->where('status', 'active');

        $heads = match ($data['target']) {
            'cow' => 1,
            'category' => (clone $active)->where('category', $data['category'])->count(),
            default => (clone $active)->count(),
        };

        if ($heads === 0) {
            throw ValidationException::withMessages(['target' => 'এই লক্ষ্যে কোনো সক্রিয় গরু নেই।']);
        }

        $type = $farm->feedTypes()->findOrFail($data['feed_type_id']);

        $record = new FeedRecord(Arr::only($data, ['fed_on', 'quantity', 'note']));
        $record->forceFill([
            'farm_id' => $farm->id,
            'feed_type_id' => $type->id,
            'unit' => $type->unit,
            'target' => $data['target'],
            'category' => $data['target'] === 'category' ? $data['category'] : null,
            'cow_id' => $data['target'] === 'cow' ? $data['cow_id'] : null,
            'head_count' => $heads,
            'created_by' => $request->user()->id,
            'source' => 'manual',
        ])->save();

        return back()->with('success', 'খাবারের রেকর্ড সংরক্ষণ হয়েছে।');
    }

    public function destroy(Farm $farm, FeedRecord $feedRecord)
    {
        Gate::authorize('manageFeed', $farm);

        $feedRecord->delete();

        return back()->with('success', 'রেকর্ডটি সরানো হয়েছে।');
    }

    // ---------- খাবারের ধরন ----------

    public function types(Farm $farm)
    {
        Gate::authorize('manageFeed', $farm);

        return view('feed.types', [
            'farm' => $farm,
            'types' => $farm->feedTypes()->withCount('records')->orderByDesc('is_active')->orderBy('name')->get(),
            'canFinance' => auth()->user()->can('manageFinance', $farm),
        ]);
    }

    public function storeType(Request $request, Farm $farm)
    {
        Gate::authorize('manageFeed', $farm);

        $data = $request->validate($this->typeRules($farm), $this->typeMessages(), $this->typeNames());

        $type = new FeedType(Arr::only($data, ['name', 'unit', 'price_per_unit']));
        $type->forceFill([
            'farm_id' => $farm->id,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ])->save();

        return back()->with('success', 'খাবারের ধরন যোগ হয়েছে।');
    }

    public function updateType(Request $request, Farm $farm, FeedType $feedType)
    {
        Gate::authorize('manageFeed', $farm);

        $data = $request->validateWithBag('edit', $this->typeRules($farm, $feedType), $this->typeMessages(), $this->typeNames());

        $feedType->update(Arr::only($data, ['name', 'unit', 'price_per_unit']));

        return back()->with('success', 'খাবারের ধরন আপডেট হয়েছে।');
    }

    public function toggleType(Farm $farm, FeedType $feedType)
    {
        Gate::authorize('manageFeed', $farm);

        $feedType->update(['is_active' => ! $feedType->is_active]);

        return back()->with('success', $feedType->is_active ? 'ধরনটি চালু হয়েছে।' : 'ধরনটি বন্ধ হয়েছে।');
    }

    public function defaults(Farm $farm)
    {
        Gate::authorize('manageFeed', $farm);

        $existing = $farm->feedTypes()->withTrashed()->pluck('name')->all();
        $added = 0;

        foreach (config('farm.feed_defaults') as $default) {
            if (in_array($default['name'], $existing, true)) {
                continue;
            }

            $type = new FeedType(['name' => $default['name'], 'unit' => $default['unit']]);
            $type->forceFill([
                'farm_id' => $farm->id,
                'is_active' => true,
                'created_by' => auth()->id(),
            ])->save();

            $added++;
        }

        return back()->with('success', $added . 'টি সাধারণ ধরন যোগ হয়েছে।');
    }

    private function typeRules(Farm $farm, ?FeedType $type = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('feed_types', 'name')->where('farm_id', $farm->id)->ignore($type?->id)],
            'unit' => ['required', Rule::in(array_keys(config('farm.feed_units')))],
            'price_per_unit' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    private function typeMessages(): array
    {
        return ['name.unique' => 'এই নামে খাবারের ধরন আগেই আছে।'];
    }

    private function typeNames(): array
    {
        return ['name' => 'নাম', 'unit' => 'একক', 'price_per_unit' => 'দাম'];
    }
}
