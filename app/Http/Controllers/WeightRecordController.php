<?php

namespace App\Http\Controllers;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\WeightRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WeightRecordController extends Controller
{
    public function store(Request $request, Farm $farm, Cow $cow)
    {
        Gate::authorize('addWeight', $cow);

        $rules = [
            'weight_kg' => ['required', 'numeric', 'min:1', 'max:2000'],
            'measured_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
        if ($cow->birth_date) {
            $rules['measured_at'][] = 'after_or_equal:' . $cow->birth_date->toDateString();
        }

        $data = $request->validate($rules, [
            'weight_kg.min' => 'ওজন কমপক্ষে ১ কেজি হতে হবে।',
            'weight_kg.max' => 'ওজন ২০০০ কেজির বেশি হতে পারে না।',
            'measured_at.after_or_equal' => 'ওজন মাপার তারিখ গরুর জন্মের আগে হতে পারে না।',
        ], [
            'weight_kg' => 'ওজন',
            'measured_at' => 'তারিখ',
        ]);

        $record = new WeightRecord($data);
        $record->forceFill([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'created_by' => $request->user()->id,
            'source' => Cow::SOURCE_MANUAL,
        ])->save();

        return back()->with('success', 'ওজন যোগ হয়েছে।');
    }

    public function destroy(Farm $farm, Cow $cow, WeightRecord $weight)
    {
        Gate::authorize('deleteWeight', $cow);

        $weight->delete();

        return back()->with('success', 'ওজনের রেকর্ড মুছে ফেলা হয়েছে।');
    }
}
