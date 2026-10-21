<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cow extends Model
{
    use BelongsToFarm, HasFactory, SoftDeletes;

    public const SOURCE_MANUAL = 'manual';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SOLD = 'sold';

    // farm_id, created_by, source ইচ্ছে করে এখানে নেই: server নিজে বসাবে
    protected $fillable = [
        'mother_id',
        'tag_number',
        'name',
        'category',
        'breed',
        'sex',
        'birth_date',
        'birth_date_estimated',
        'sire_name',
        'acquisition_type',
        'purchase_date',
        'purchase_price',
        'opening_value',
        'status',
        'exit_date',
        'photo_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'purchase_date' => 'date',
            'exit_date' => 'date',
            'birth_date_estimated' => 'boolean',
            'purchase_price' => 'decimal:2',
            'opening_value' => 'decimal:2',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function mother(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mother_id')->withTrashed();
    }

    public function calves(): HasMany
    {
        return $this->hasMany(self::class, 'mother_id');
    }

    // রুটের {weight} প্যারামিটারের scoped binding এই নাম খোঁজে
    public function weights(): HasMany
    {
        return $this->hasMany(WeightRecord::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ageLabel(): ?string
    {
        if (! $this->birth_date) {
            return null;
        }

        $end = $this->exit_date ?: now();
        $diff = $this->birth_date->diff($end);

        if ($diff->y > 0) {
            $text = $diff->y . ' বছর' . ($diff->m > 0 ? ' ' . $diff->m . ' মাস' : '');
        } elseif ($diff->m > 0) {
            $text = $diff->m . ' মাস' . ($diff->d > 0 ? ' ' . $diff->d . ' দিন' : '');
        } else {
            $text = $diff->d . ' দিন';
        }

        return ($this->birth_date_estimated ? 'প্রায় ' : '') . $text;
    }

    /** বিনিয়োগের ভিত্তি: কেনা হলে ক্রয়মূল্য, আগে থেকে থাকলে প্রারম্ভিক মূল্য, জন্ম হলে ০ */
    public function baseValue(): float
    {
        return match ($this->acquisition_type) {
            'purchased' => (float) $this->purchase_price,
            'existing' => (float) $this->opening_value,
            default => 0.0,
        };
    }

    public function baseValueLabel(): string
    {
        return match ($this->acquisition_type) {
            'purchased' => 'ক্রয়মূল্য',
            'existing' => 'প্রারম্ভিক মূল্য',
            default => 'শুরুর মূল্য',
        };
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }

    public function healthEvents(): HasMany
    {
        return $this->hasMany(HealthEvent::class);
    }
}
