<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HealthService
{
    public function __construct(private LedgerService $ledger) {}

    /**
     * এক বা একাধিক গরুর জন্য রেকর্ড। $cost থাকলে একটাই খরচ বানায় (expenses-এ)।
     * $cost = ['amount' => float, 'pay' => ['mode', 'amount', 'method', 'date']]
     */
    public function record(Farm $farm, array $cowIds, array $data, ?array $cost, User $user): Collection
    {
        return DB::transaction(function () use ($farm, $cowIds, $data, $cost, $user) {
            $cows = $farm->cows()->whereIn('id', $cowIds)->get();

            abort_if($cows->count() !== count(array_unique($cowIds)), 422, 'গরুর তালিকায় ভুল আছে।');

            $expenseId = null;

            if ($cost) {
                $expense = $this->ledger->createExpense($farm, [
                    'category' => 'medicine',
                    'cow_id' => $cows->count() === 1 ? $cows->first()->id : null,
                    'expense_date' => $data['event_date'],
                    'description' => $data['title'] . ($cows->count() > 1 ? ' (' . $cows->count() . 'টি গরু)' : ''),
                    'vendor_name' => $data['vet_name'] ?? null,
                ], (float) $cost['amount'], $cost['pay'], $user);

                $expenseId = $expense->id;
            }

            $events = collect();

            foreach ($cows as $cow) {
                $event = new HealthEvent($data);
                $event->forceFill([
                    'farm_id' => $farm->id,
                    'cow_id' => $cow->id,
                    'expense_id' => $expenseId,
                    'follow_up_status' => ! empty($data['next_due_date']) ? 'open' : null,
                    'created_by' => $user->id,
                    'source' => 'manual',
                ])->save();

                $this->closeOlderFollowUps($event);
                $events->push($event);
            }

            return $events;
        });
    }

    /** আগামী $days দিনের মধ্যে (এবং দেরি হয়ে যাওয়া) সক্রিয় গরুর কাজ, আগে দেরি হওয়াগুলো */
    public function upcoming(Farm $farm, int $days = 30): Collection
    {
        return $farm->healthEvents()
            ->where('follow_up_status', 'open')
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', now()->addDays($days)->toDateString())
            ->whereHas('cow', fn($q) => $q->where('status', 'active')->whereNull('deleted_at'))
            ->with('cow')
            ->orderBy('next_due_date')
            ->get();
    }

    /** একই গরুর একই টিকা (বা কৃমিনাশক, পরীক্ষা) আবার হলে আগের "পরের তারিখ" সম্পন্ন হয় */
    private function closeOlderFollowUps(HealthEvent $event): void
    {
        if ($event->kind === 'treatment' || $event->vaccine_code === 'other') {
            return;
        }

        HealthEvent::withoutGlobalScope('farm')
            ->where('farm_id', $event->farm_id)
            ->where('cow_id', $event->cow_id)
            ->where('kind', $event->kind)
            ->where('follow_up_status', 'open')
            ->where('id', '!=', $event->id)
            ->when(
                $event->vaccine_code,
                fn($q, $code) => $q->where('vaccine_code', $code),
                fn($q) => $q->whereNull('vaccine_code')
            )
            ->update(['follow_up_status' => 'done']);
    }
}
