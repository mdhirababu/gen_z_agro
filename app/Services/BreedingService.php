<?php

namespace App\Services;

use App\Models\BreedingEvent;
use App\Models\Cow;
use App\Models\Farm;
use App\Models\User;
use App\Models\WeightRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BreedingService
{
    public function __construct(private LedgerService $ledger) {}

    /** নতুন সেবা। $cost থাকলে একটা খরচও বানায় (expenses-এ, "প্রজনন" ধরনে)। */
    public function record(Farm $farm, int $cowId, array $data, ?array $cost, User $user): BreedingEvent
    {
        return DB::transaction(function () use ($farm, $cowId, $data, $cost, $user) {
            $cow = $farm->cows()->whereKey($cowId)->lockForUpdate()->firstOrFail();

            $pregnant = BreedingEvent::withoutGlobalScope('farm')
                ->where('cow_id', $cow->id)
                ->where('status', 'pregnant')
                ->exists();

            if ($pregnant) {
                throw ValidationException::withMessages([
                    'cow_id' => 'এই গরুটি আগে থেকেই গর্ভবতী। আগে প্রসব, গর্ভপাত বা "গর্ভ নেই" নথিভুক্ত করুন।',
                ]);
            }

            $expenseId = null;

            if ($cost) {
                $label = config('farm.breeding_methods')[$data['method']] ?? 'প্রজনন';

                $expense = $this->ledger->createExpense($farm, [
                    'category' => 'breeding',
                    'cow_id' => $cow->id,
                    'expense_date' => $data['service_date'],
                    'description' => 'প্রজনন: ' . $label . (! empty($data['sire_name']) ? ', ' . $data['sire_name'] : ''),
                    'vendor_name' => $data['technician'] ?? null,
                ], (float) $cost['amount'], $cost['pay'], $user);

                $expenseId = $expense->id;
            }

            // আগের অমীমাংসিত সেবা ধরে নেওয়া হয় "গর্ভ হয়নি"
            BreedingEvent::withoutGlobalScope('farm')
                ->where('cow_id', $cow->id)
                ->where('status', 'served')
                ->update([
                    'status' => 'open',
                    'next_check_date' => null,
                    'outcome_date' => $data['service_date'],
                ]);

            $event = new BreedingEvent($data);
            $event->forceFill([
                'farm_id' => $farm->id,
                'cow_id' => $cow->id,
                'status' => 'served',
                'expense_id' => $expenseId,
                'created_by' => $user->id,
                'source' => 'manual',
            ])->save();

            return $event;
        });
    }

    /** গর্ভ পরীক্ষার ফল: pregnant বা open */
    public function recordCheck(BreedingEvent $event, string $result, string $date, ?string $expected): BreedingEvent
    {
        return DB::transaction(function () use ($event, $result, $date, $expected) {
            $locked = BreedingEvent::withoutGlobalScope('farm')->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['served', 'pregnant'], true)) {
                throw ValidationException::withMessages(['result' => 'এই সেবার ফল আগেই চূড়ান্ত হয়ে গেছে।']);
            }

            if ($result === 'pregnant') {
                $fallback = Carbon::parse($locked->service_date)
                    ->addDays((int) config('farm.breeding.gestation_days'))
                    ->toDateString();

                $locked->forceFill([
                    'status' => 'pregnant',
                    'checked_on' => $date,
                    'next_check_date' => null,
                    'expected_calving_date' => $expected ?: ($locked->expected_calving_date?->toDateString() ?? $fallback),
                ]);
            } else {
                $locked->forceFill([
                    'status' => 'open',
                    'checked_on' => $date,
                    'outcome_date' => $date,
                    'next_check_date' => null,
                ]);
            }

            $locked->save();

            return $locked;
        });
    }

    public function recordAbortion(BreedingEvent $event, string $date): BreedingEvent
    {
        return DB::transaction(function () use ($event, $date) {
            $locked = BreedingEvent::withoutGlobalScope('farm')->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pregnant') {
                throw ValidationException::withMessages([
                    'outcome_date' => 'গর্ভপাত শুধু গর্ভবতী অবস্থা থেকে নথিভুক্ত করা যায়।',
                ]);
            }

            $locked->forceFill(['status' => 'aborted', 'outcome_date' => $date, 'next_check_date' => null])->save();

            return $locked;
        });
    }

    /**
     * প্রসব নথিভুক্ত: বাছুর গরু হিসেবে তৈরি, সেবা "calved", মা গরুর ধরন হালনাগাদ।
     * সবকিছু এক লেনদেনে: কোথাও আটকালে কিছুই তৈরি হয় না।
     *
     * @param array $calves  [['tag_number','sex','name','weight_kg','alive'], ...]
     */
    public function recordCalving(Farm $farm, BreedingEvent $event, string $date, array $calves, bool $updateDam, User $user): Collection
    {
        return DB::transaction(function () use ($farm, $event, $date, $calves, $updateDam, $user) {
            $locked = BreedingEvent::withoutGlobalScope('farm')->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['served', 'pregnant'], true)) {
                throw ValidationException::withMessages(['calved_on' => 'এই সেবার ফল আগেই চূড়ান্ত হয়ে গেছে।']);
            }

            $dam = Cow::withoutGlobalScope('farm')
                ->where('farm_id', $farm->id)
                ->whereKey($locked->cow_id)
                ->lockForUpdate()
                ->firstOrFail();

            // জীবিত বাছুর প্ল্যানের গরুর সীমায় গোনা হয়
            $aliveCount = collect($calves)
                ->filter(fn($c) => filter_var($c['alive'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->count();

            Farm::whereKey($farm->id)->lockForUpdate()->first();
            $left = $farm->fresh()->cowSlotsLeft();

            if ($left !== null && $aliveCount > $left) {
                throw ValidationException::withMessages([
                    'calves' => "প্ল্যানে আর {$left}টি গরুর জায়গা আছে, কিন্তু {$aliveCount}টি জীবিত বাছুর নথিভুক্ত করতে যাচ্ছেন। প্ল্যান আপগ্রেড করুন।",
                ]);
            }

            $created = collect();

            foreach ($calves as $c) {
                $isAlive = filter_var($c['alive'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $calf = new Cow([
                    'mother_id' => $dam->id,
                    'tag_number' => $c['tag_number'],
                    'name' => $c['name'] ?? null,
                    'category' => 'calf',
                    'sex' => $c['sex'],
                    'birth_date' => $date,
                    'birth_date_estimated' => false,
                    'sire_name' => $locked->sire_name,
                    'acquisition_type' => 'born',
                    'purchase_price' => 0,
                    'opening_value' => 0,
                    'status' => $isAlive ? Cow::STATUS_ACTIVE : 'dead',
                    'exit_date' => $isAlive ? null : $date,
                ]);
                $calf->forceFill([
                    'farm_id' => $farm->id,
                    'created_by' => $user->id,
                    'source' => Cow::SOURCE_MANUAL,
                ])->save();

                if (! empty($c['weight_kg'])) {
                    $weight = new WeightRecord(['weight_kg' => $c['weight_kg'], 'measured_at' => $date]);
                    $weight->forceFill([
                        'farm_id' => $farm->id,
                        'cow_id' => $calf->id,
                        'created_by' => $user->id,
                        'source' => Cow::SOURCE_MANUAL,
                    ])->save();
                }

                $created->push($calf);
            }

            $locked->forceFill([
                'status' => 'calved',
                'outcome_date' => $date,
                'next_check_date' => null,
            ])->save();

            // প্রথমবার বা শুকনোর পর প্রসব হলে গাভি এখন দুধ দেবে
            if ($updateDam && in_array($dam->category, ['heifer', 'dry'], true)) {
                $dam->forceFill(['category' => 'milking'])->save();
            }

            return $created;
        });
    }

    /** গর্ভ পরীক্ষার সময় হয়েছে বা কাছে এসেছে এমন সেবা (সক্রিয় গরুর) */
    public function dueChecks(Farm $farm, int $days): Collection
    {
        return $farm->breedingEvents()
            ->where('status', 'served')
            ->whereNotNull('next_check_date')
            ->whereDate('next_check_date', '<=', now()->addDays($days)->toDateString())
            ->whereHas('cow', fn($q) => $q->where('status', 'active')->whereNull('deleted_at'))
            ->with('cow')
            ->orderBy('next_check_date')
            ->get();
    }

    /** প্রসবের সম্ভাব্য তারিখ কাছে এসেছে বা পেরিয়ে গেছে এমন গর্ভবতী গরু */
    public function dueCalvings(Farm $farm, int $days): Collection
    {
        return $farm->breedingEvents()
            ->where('status', 'pregnant')
            ->whereNotNull('expected_calving_date')
            ->whereDate('expected_calving_date', '<=', now()->addDays($days)->toDateString())
            ->whereHas('cow', fn($q) => $q->where('status', 'active')->whereNull('deleted_at'))
            ->with('cow')
            ->orderBy('expected_calving_date')
            ->get();
    }
}
