<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HealthEvent extends Model
{
    use BelongsToFarm, SoftDeletes;

    // farm_id, cow_id, expense_id, follow_up_status, created_by, source এখানে নেই: server বসাবে
    protected $fillable = [
        'kind',
        'event_date',
        'vaccine_code',
        'title',
        'details',
        'medicine',
        'vet_name',
        'outcome',
        'milk_withdrawal_until',
        'next_due_date',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'milk_withdrawal_until' => 'date',
            'next_due_date' => 'date',
        ];
    }

    public function cow(): BelongsTo
    {
        return $this->belongsTo(Cow::class)->withTrashed();
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindMeta(): array
    {
        return config('farm.health_kinds')[$this->kind]
            ?? ['label' => $this->kind, 'icon' => 'bi-heart-pulse', 'tone' => 'sand', 'pill' => 'gray'];
    }

    /** আজ থেকে কত দিন পরে (ঋণাত্মক হলে দেরি) */
    public function daysToDue(): ?int
    {
        if (! $this->next_due_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->next_due_date->copy()->startOfDay(), false);
    }

    public function isOverdue(): bool
    {
        return $this->follow_up_status === 'open' && $this->daysToDue() !== null && $this->daysToDue() < 0;
    }

    public function withdrawalActive(): bool
    {
        return $this->milk_withdrawal_until && $this->milk_withdrawal_until->gte(now()->startOfDay());
    }
}
