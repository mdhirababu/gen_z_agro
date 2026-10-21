<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersByMonth;
use App\Models\Farm;
use App\Models\MilkRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MilkController extends Controller
{
    use FiltersByMonth;

    /** মাসিক সারসংক্ষেপ */
    public function index(Request $request, Farm $farm)
    {
        Gate::authorize('view', $farm);

        $month = $this->monthParam($request);
        if ($month === 'all') {
            $month = now()->format('Y-m');
        }
        [$start, $end] = $this->monthRange($month);

        $inMonth = fn() => $farm->milkRecords()
            ->whereDate('milk_date', '>=', $start)
            ->whereDate('milk_date', '<=', $end);

        $totals = $inMonth()->selectRaw(
            'coalesce(sum(session_1), 0) as s1, coalesce(sum(session_2), 0) as s2, coalesce(sum(session_3), 0) as s3, '
                . 'coalesce(sum(total_liters), 0) as total, count(*) as records, '
                . 'count(distinct cow_id) as cows, count(distinct milk_date) as days'
        )->first();

        $daily = $inMonth()
            ->selectRaw('milk_date, sum(session_1) as s1, sum(session_2) as s2, sum(session_3) as s3, sum(total_liters) as total, count(*) as records')
            ->groupBy('milk_date')
            ->orderByDesc('milk_date')
            ->get();

        $perCow = $inMonth()
            ->selectRaw('cow_id, sum(total_liters) as total, count(*) as days')
            ->groupBy('cow_id')
            ->orderByDesc('total')
            ->get();

        $cows = $farm->cows()->withTrashed()->whereIn('id', $perCow->pluck('cow_id'))->get()->keyBy('id');

        $days = (int) $totals->days;
        $avg = $days > 0 ? round((float) $totals->total / $days, 1) : 0;

        // উৎপাদন বনাম বিক্রি: শুধু মালিক ও ম্যানেজারের জন্য
        $sold = null;
        if (auth()->user()->can('manageFinance', $farm)) {
            $sold = (float) $farm->sales()
                ->where('sale_type', 'milk')
                ->where('status', 'active')
                ->whereDate('sold_at', '>=', $start)
                ->whereDate('sold_at', '<=', $end)
                ->sum('quantity');
        }

        return view('milk.index', [
            'farm' => $farm,
            'month' => $month,
            'months' => $this->monthOptions($month),
            'totals' => $totals,
            'daily' => $daily,
            'perCow' => $perCow,
            'cows' => $cows,
            'avg' => $avg,
            'sold' => $sold,
        ]);
    }

    /** একদিনের সব গাভির দুধ এক পাতায় */
    public function entry(Request $request, Farm $farm)
    {
        Gate::authorize('view', $farm);

        $date = $this->dateParam($request);

        $cows = $farm->cows()
            ->where('status', 'active')
            ->where('sex', 'female')
            ->where('category', 'milking')
            ->orderBy('tag_number')
            ->get();

        $records = $farm->milkRecords()
            ->whereDate('milk_date', $date->toDateString())
            ->get()
            ->keyBy('cow_id');

        // খালি ঘর দেখাতে: ০ হলে ঘর ফাঁকা
        $initial = $cows->mapWithKeys(function ($cow) use ($records) {
            $r = $records->get($cow->id);
            $v = fn(int $n) => ($r && (float) $r->{'session_' . $n} > 0) ? (string) (float) $r->{'session_' . $n} : '';

            return [$cow->id => ['1' => $v(1), '2' => $v(2), '3' => $v(3)]];
        })->all();

        // ওষুধের কারণে দুধ বিক্রি বন্ধ আছে এমন গরু
        $withdrawal = $farm->healthEvents()
            ->whereDate('milk_withdrawal_until', '>=', $date->toDateString())
            ->whereDate('event_date', '<=', $date->toDateString())
            ->get()
            ->groupBy('cow_id')
            ->map(fn($group) => $group->max('milk_withdrawal_until'));

        return view('milk.entry', [
            'farm' => $farm,
            'date' => $date,
            'prev' => $date->copy()->subDay()->toDateString(),
            'next' => $date->isToday() ? null : $date->copy()->addDay()->toDateString(),
            'cows' => $cows,
            'initial' => $initial,
            'withdrawal' => $withdrawal,
        ]);
    }

    public function save(Request $request, Farm $farm)
    {
        Gate::authorize('view', $farm);

        $rules = [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'rows' => ['required', 'array', 'max:500'],
        ];
        $messages = ['date.before_or_equal' => 'ভবিষ্যতের তারিখে দুধ লেখা যায় না।'];

        foreach ([1, 2, 3] as $n) {
            $rules["rows.*.$n"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $messages["rows.*.$n.numeric"] = 'দুধের পরিমাণ সংখ্যা হতে হবে।';
            $messages["rows.*.$n.min"] = 'দুধের পরিমাণ ঋণাত্মক হতে পারে না।';
            $messages["rows.*.$n.max"] = 'এক বারে ১০০ লিটারের বেশি দেওয়া যাবে না।';
        }

        $data = $request->validate($rules, $messages, ['date' => 'তারিখ']);

        $rows = $data['rows'];
        $ids = array_map('intval', array_keys($rows));

        // শুধু এই খামারের সক্রিয় মাদি গরু, নইলে 422
        $eligible = $farm->cows()
            ->where('status', 'active')
            ->where('sex', 'female')
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        abort_if(count(array_unique($ids)) !== count($eligible), 422, 'অনুমোদিত নয় এমন গরুর তথ্য এসেছে।');

        $date = Carbon::parse($data['date'])->toDateString();
        $saved = 0;

        DB::transaction(function () use ($rows, $date, $farm, $request, &$saved) {
            foreach ($rows as $cowId => $vals) {
                $s = [1 => $vals[1] ?? null, 2 => $vals[2] ?? null, 3 => $vals[3] ?? null];

                // তিন ঘরই খালি হলে বাদ
                if (collect($s)->every(fn($v) => $v === null || $v === '')) {
                    continue;
                }

                $record = MilkRecord::withoutGlobalScope('farm')
                    ->where('cow_id', (int) $cowId)
                    ->whereDate('milk_date', $date)
                    ->first() ?? new MilkRecord();

                $isNew = ! $record->exists;

                $record->forceFill([
                    'farm_id' => $farm->id,
                    'cow_id' => (int) $cowId,
                    'milk_date' => $date,
                    'session_1' => (float) ($s[1] ?? 0),
                    'session_2' => (float) ($s[2] ?? 0),
                    'session_3' => (float) ($s[3] ?? 0),
                ]);

                if ($isNew) {
                    $record->forceFill(['created_by' => $request->user()->id, 'source' => 'manual']);
                }

                $record->save();
                $saved++;
            }
        });

        if ($saved === 0) {
            return back()->with('error', 'কোনো গরুর দুধের পরিমাণ লেখা হয়নি।');
        }

        return redirect()
            ->route('farms.milk.entry', [$farm, 'date' => $date])
            ->with('success', $saved . 'টি গরুর দুধের হিসাব সংরক্ষণ হয়েছে।');
    }

    private function dateParam(Request $request): Carbon
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $request->query('date', now()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }

        return $date->isFuture() ? now()->startOfDay() : $date;
    }
}
