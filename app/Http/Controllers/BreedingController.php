<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordCalvingRequest;
use App\Http\Requests\SaveBreedingRequest;
use App\Models\BreedingEvent;
use App\Models\Farm;
use App\Services\BreedingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BreedingController extends Controller
{
    public function index(Request $request, Farm $farm)
    {
        Gate::authorize('view', $farm);

        $status = (string) $request->query('status', '');
        $status = array_key_exists($status, config('farm.breeding_statuses')) ? $status : '';
        $cowId = (string) $request->query('cow', '');

        $base = $farm->breedingEvents()
            ->when($status !== '', fn($q) => $q->where('status', $status))
            ->when($cowId !== '' && ctype_digit($cowId), fn($q) => $q->where('cow_id', (int) $cowId));

        $counts = $farm->breedingEvents()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $activeCow = fn($q) => $q->where('status', 'active')->whereNull('deleted_at');
        $limit = now()->addDays(30)->toDateString();

        return view('breeding.index', [
            'farm' => $farm,
            'events' => (clone $base)->with('cow')->orderByDesc('service_date')->orderByDesc('id')->paginate(20)->withQueryString(),
            'counts' => $counts,
            'filters' => ['status' => $status, 'cow' => $cowId],
            'cows' => $farm->cows()->where('sex', 'female')->orderBy('tag_number')->get(['id', 'tag_number', 'name']),
            'pregnantCount' => $farm->breedingEvents()->where('status', 'pregnant')->whereHas('cow', $activeCow)->count(),
            'soonCount' => $farm->breedingEvents()->where('status', 'pregnant')
                ->whereDate('expected_calving_date', '<=', $limit)->whereHas('cow', $activeCow)->count(),
            'checkCount' => $farm->breedingEvents()->where('status', 'served')->whereHas('cow', $activeCow)->count(),
        ]);
    }

    public function create(Request $request, Farm $farm)
    {
        Gate::authorize('view', $farm);

        return view('breeding.create', [
            'farm' => $farm,
            'cows' => $farm->cows()
                ->where('status', 'active')
                ->where('sex', 'female')
                ->where('category', '!=', 'calf')
                ->orderBy('tag_number')
                ->get(['id', 'tag_number', 'name', 'category']),
            'pregnantIds' => $farm->breedingEvents()->where('status', 'pregnant')->pluck('cow_id')->all(),
            'selected' => (int) $request->query('cow'),
            'canFinance' => $request->user()->can('manageFinance', $farm),
        ]);
    }

    public function store(SaveBreedingRequest $request, Farm $farm, BreedingService $breeding)
    {
        $event = $breeding->record(
            $farm,
            (int) $request->validated()['cow_id'],
            $request->payload(),
            $request->cost(),
            $request->user()
        );

        return redirect()
            ->route('farms.breeding.show', [$farm, $event])
            ->with('success', 'প্রজননের সেবা নথিভুক্ত হয়েছে।');
    }

    public function show(Farm $farm, BreedingEvent $breedingEvent)
    {
        Gate::authorize('view', $farm);

        $breedingEvent->load('cow', 'expense', 'creator');

        return view('breeding.show', [
            'farm' => $farm,
            'event' => $breedingEvent,
            'calves' => $breedingEvent->calvesBorn(),
            'slotsLeft' => $farm->cowSlotsLeft(),
            'canFinance' => auth()->user()->can('manageFinance', $farm),
            'canManage' => auth()->user()->can('manageHealth', $farm),
        ]);
    }

    public function edit(Farm $farm, BreedingEvent $breedingEvent)
    {
        Gate::authorize('manageHealth', $farm);

        return view('breeding.edit', ['farm' => $farm, 'event' => $breedingEvent->load('cow')]);
    }

    public function update(SaveBreedingRequest $request, Farm $farm, BreedingEvent $breedingEvent)
    {
        $breedingEvent->fill($request->payload());

        // গর্ভ পরীক্ষার তারিখ শুধু "ফল বাকি" অবস্থায় অর্থবহ
        if ($breedingEvent->status !== 'served') {
            $breedingEvent->next_check_date = null;
        }

        $breedingEvent->save();

        return redirect()
            ->route('farms.breeding.show', [$farm, $breedingEvent])
            ->with('success', 'সেবার তথ্য আপডেট হয়েছে।');
    }

    public function destroy(Farm $farm, BreedingEvent $breedingEvent)
    {
        Gate::authorize('manageHealth', $farm);

        // প্রসবে তৈরি বাছুর ও জুড়ে থাকা খরচ বদলায় না
        $breedingEvent->delete();

        return redirect()
            ->route('farms.breeding.index', $farm)
            ->with('success', 'রেকর্ডটি সরানো হয়েছে।');
    }

    public function check(Request $request, Farm $farm, BreedingEvent $breedingEvent, BreedingService $breeding)
    {
        Gate::authorize('view', $farm);

        $service = $breedingEvent->service_date->toDateString();

        $data = $request->validate([
            'result' => ['required', Rule::in(['pregnant', 'open'])],
            'checked_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $service],
            'expected_calving_date' => ['nullable', 'date', 'after:' . $service],
        ], [
            'checked_on.after_or_equal' => 'পরীক্ষার তারিখ সেবার তারিখের আগে হতে পারে না।',
            'checked_on.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
            'expected_calving_date.after' => 'প্রসবের তারিখ সেবার তারিখের পরে হতে হবে।',
        ], ['result' => 'ফল', 'checked_on' => 'পরীক্ষার তারিখ', 'expected_calving_date' => 'প্রসবের সম্ভাব্য তারিখ']);

        $breeding->recordCheck($breedingEvent, $data['result'], $data['checked_on'], $data['expected_calving_date'] ?? null);

        return redirect()
            ->route('farms.breeding.show', [$farm, $breedingEvent])
            ->with('success', $data['result'] === 'pregnant' ? 'গর্ভবতী হিসেবে নথিভুক্ত হয়েছে।' : 'গর্ভ হয়নি হিসেবে নথিভুক্ত হয়েছে।');
    }

    public function calve(RecordCalvingRequest $request, Farm $farm, BreedingEvent $breedingEvent, BreedingService $breeding)
    {
        $data = $request->validated();

        $calves = $breeding->recordCalving(
            $farm,
            $breedingEvent,
            $data['calved_on'],
            $data['calves'],
            $request->boolean('update_dam'),
            $request->user()
        );

        return redirect()
            ->route('farms.breeding.show', [$farm, $breedingEvent])
            ->with('success', 'প্রসব নথিভুক্ত হয়েছে, ' . $calves->count() . 'টি বাছুর যোগ হয়েছে।');
    }

    public function abortPregnancy(Request $request, Farm $farm, BreedingEvent $breedingEvent, BreedingService $breeding)
    {
        Gate::authorize('view', $farm);

        $data = $request->validate([
            'outcome_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $breedingEvent->service_date->toDateString()],
        ], [], ['outcome_date' => 'গর্ভপাতের তারিখ']);

        $breeding->recordAbortion($breedingEvent, $data['outcome_date']);

        return redirect()
            ->route('farms.breeding.show', [$farm, $breedingEvent])
            ->with('success', 'গর্ভপাত নথিভুক্ত হয়েছে।');
    }
}
