<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class BreedingEvent extends Model
{
    use BelongsToFarm, SoftDeletes;

    // farm_id, cow_id, status, expense_id, created_by, source এখানে নেই: শুধু service বদলাবে
    protected $fillable = [
        'service_date',
        'method',
        'sire_name',
        'technician',
        'next_check_date',
        'expected_calving_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'next_check_date' => 'date',
            'checked_on' => 'date',
            'expected_calving_date' => 'date',
            'outcome_date' => 'date',
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

    public function statusMeta(): array
    {
        return config('farm.breeding_statuses')[$this->status] ?? ['label' => $this->status, 'pill' => 'gray'];
    }

    public function methodLabel(): string
    {
        return config('farm.breeding_methods')[$this->method] ?? $this->method;
    }

    /** এই প্রসবে জন্মানো বাছুর (মা + জন্মতারিখ মিলিয়ে) */
    public function calvesBorn(): Collection
    {
        if ($this->status !== 'calved' || ! $this->outcome_date) {
            return collect();
        }

        return Cow::withoutGlobalScope('farm')
            ->where('farm_id', $this->farm_id)
            ->where('mother_id', $this->cow_id)
            ->whereDate('birth_date', $this->outcome_date->toDateString())
            ->orderBy('tag_number')
            ->get();
    }
}
