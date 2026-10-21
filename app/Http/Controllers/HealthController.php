<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveHealthRequest;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Services\HealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HealthController extends Controller
{
    public function index(Request $request, Farm $farm, HealthService $health)
    {
        Gate::authorize('view', $farm);

        $kind = (string) $request->query('kind', '');
        $kind = array_key_exists($kind, config('farm.health_kinds')) ? $kind : '';
        $cowId = (string) $request->query('cow', '');
        $ongoing = $request->boolean('ongoing');

        $base = $farm->healthEvents()
            ->when($kind !== '', fn($q) => $q->where('kind', $kind))
            ->when($cowId !== '' && ctype_digit($cowId), fn($q) => $q->where('cow_id', (int) $cowId))
            ->when($ongoing, fn($q) => $q->where('kind', 'treatment')->where('outcome', 'ongoing'));

        $counts = $farm->healthEvents()->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind');

        $events = (clone $base)
            ->with(['cow', 'expense'])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('health.index', [
            'farm' => $farm,
            'events' => $events,
            'counts' => $counts,
            'filters' => ['kind' => $kind, 'cow' => $cowId, 'ongoing' => $ongoing],
            'cows' => $farm->cows()->orderBy('tag_number')->get(['id', 'tag_number', 'name']),
            'taskCount' => $health->upcoming($farm, 30)->count(),
            'canFinance' => $request->user()->can('manageFinance', $farm),
            'canManage' => $request->user()->can('manageHealth', $farm),
        ]);
    }

    public function create(Request $request, Farm $farm)
    {
        Gate::authorize('view', $farm);

        $cows = $farm->cows()->where('status', 'active')->orderBy('tag_number')->get(['id', 'tag_number', 'name', 'category']);

        // ?cow=5 (একটি) বা ?cows[]=5&cows[]=6 (একাধিক) থেকে আগে থেকে বাছা
        $selected = collect((array) $request->query('cow'))
            ->merge((array) $request->query('cows'))
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $cows->contains('id', $v))
            ->unique()
            ->values()
            ->all();

        $kind = (string) $request->query('kind', 'treatment');
        if (! array_key_exists($kind, config('farm.health_kinds'))) {
            $kind = 'treatment';
        }

        $vaccine = (string) $request->query('vaccine', 'fmd');
        if (! array_key_exists($vaccine, config('farm.vaccines'))) {
            $vaccine = 'fmd';
        }

        return view('health.create', [
            'farm' => $farm,
            'cows' => $cows,
            'selected' => $selected,
            'kind' => $kind,
            'vaccine' => $vaccine,
            'canFinance' => $request->user()->can('manageFinance', $farm),
        ]);
    }

    public function store(SaveHealthRequest $request, Farm $farm, HealthService $health)
    {
        $events = $health->record(
            $farm,
            $request->cowIds(),
            $request->payload(),
            $request->cost(),
            $request->user()
        );

        return redirect()
            ->route('farms.health.index', $farm)
            ->with('success', $events->count() . 'টি স্বাস্থ্য রেকর্ড সংরক্ষণ হয়েছে।');
    }

    public function edit(Farm $farm, HealthEvent $healthEvent)
    {
        Gate::authorize('manageHealth', $farm);

        $healthEvent->load('cow', 'expense');

        return view('health.edit', [
            'farm' => $farm,
            'event' => $healthEvent,
            'canFinance' => auth()->user()->can('manageFinance', $farm),
        ]);
    }

    public function update(SaveHealthRequest $request, Farm $farm, HealthEvent $healthEvent)
    {
        $healthEvent->fill($request->payload());

        // পরের তারিখ বদলালে আবার "খোলা" হয়, মুছলে কাজই থাকে না
        if ($healthEvent->isDirty('next_due_date')) {
            $healthEvent->follow_up_status = $healthEvent->next_due_date ? 'open' : null;
        }

        $healthEvent->save();

        return redirect()
            ->route('farms.health.index', $farm)
            ->with('success', 'রেকর্ড আপডেট হয়েছে।');
    }

    public function destroy(Farm $farm, HealthEvent $healthEvent)
    {
        Gate::authorize('manageHealth', $farm);

        // জুড়ে থাকা খরচ (থাকলে) বদলায় না, সেটা খরচের পাতা থেকে বাতিল করতে হয়
        $healthEvent->delete();

        return back()->with('success', 'রেকর্ডটি সরানো হয়েছে।');
    }

    public function recover(Farm $farm, HealthEvent $healthEvent)
    {
        Gate::authorize('view', $farm);

        if ($healthEvent->kind === 'treatment') {
            $healthEvent->update(['outcome' => 'recovered']);
        }

        return back()->with('success', 'সুস্থ হিসেবে চিহ্নিত করা হয়েছে।');
    }

    public function done(Farm $farm, HealthEvent $healthEvent)
    {
        Gate::authorize('view', $farm);

        $healthEvent->forceFill(['follow_up_status' => 'done'])->save();

        return back()->with('success', 'কাজটি সম্পন্ন হিসেবে চিহ্নিত করা হয়েছে।');
    }

    public function tasks(Farm $farm, HealthService $health)
    {
        Gate::authorize('view', $farm);

        return view('health.tasks', [
            'farm' => $farm,
            'tasks' => $health->upcoming($farm, 30),
            'later' => $farm->healthEvents()
                ->where('follow_up_status', 'open')
                ->whereDate('next_due_date', '>', now()->addDays(30)->toDateString())
                ->count(),
        ]);
    }
}
