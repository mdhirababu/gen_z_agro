<?php

namespace App\Services;

use App\Models\Farm;
use App\Support\FarmTask;
use Illuminate\Support\Collection;

class TaskService
{
    public function __construct(
        private HealthService $health,
        private BreedingService $breeding,
    ) {}

    /** @return Collection<int, FarmTask> তারিখ অনুযায়ী সাজানো, দেরি হওয়াগুলো আগে */
    public function upcoming(Farm $farm, int $days = 30): Collection
    {
        $tasks = collect();

        foreach ($this->health->upcoming($farm, $days) as $e) {
            $meta = $e->kindMeta();

            $tasks->push(new FarmTask(
                'health',
                $e->cow,
                $e->title,
                $meta['icon'],
                $meta['tone'],
                $e->next_due_date,
                'এখন করুন',
                route('farms.health.create', [$farm, 'kind' => $e->kind, 'vaccine' => $e->vaccine_code, 'cow' => $e->cow_id]),
                route('farms.health.done', [$farm, $e]),
            ));
        }

        foreach ($this->breeding->dueChecks($farm, $days) as $b) {
            $tasks->push(new FarmTask(
                'breeding_check',
                $b->cow,
                'গর্ভ পরীক্ষা',
                'bi-clipboard-pulse',
                'mint',
                $b->next_check_date,
                'ফল লিখুন',
                route('farms.breeding.show', [$farm, $b]),
            ));
        }

        foreach ($this->breeding->dueCalvings($farm, $days) as $b) {
            $tasks->push(new FarmTask(
                'breeding_calving',
                $b->cow,
                'প্রসবের সম্ভাব্য তারিখ',
                'bi-heart',
                'rose',
                $b->expected_calving_date,
                'প্রসব নথিভুক্ত',
                route('farms.breeding.show', [$farm, $b]),
            ));
        }

        return $tasks->sortBy(fn(FarmTask $t) => $t->date->timestamp)->values();
    }

    /** $days দিনের পরের কাজ কয়টি */
    public function laterCount(Farm $farm, int $days = 30): int
    {
        $limit = now()->addDays($days)->toDateString();

        return $farm->healthEvents()->where('follow_up_status', 'open')->whereDate('next_due_date', '>', $limit)->count()
            + $farm->breedingEvents()->where('status', 'served')->whereDate('next_check_date', '>', $limit)->count()
            + $farm->breedingEvents()->where('status', 'pregnant')->whereDate('expected_calving_date', '>', $limit)->count();
    }
}
