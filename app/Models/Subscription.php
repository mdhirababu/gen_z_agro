<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\Auditable;

class Subscription extends Model
{
    use Auditable;

    public const TRIAL = 'trial';
    public const ACTIVE = 'active';
    public const PAST_DUE = 'past_due';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'farm_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * তারিখ থেকে হিসাব করা আসল অবস্থা। scheduler না চললেও ভুল হবে না।
     */
    public function currentStatus(): string
    {
        if ($this->status === self::CANCELLED) {
            return self::CANCELLED;
        }

        if ($this->status === self::TRIAL) {
            return $this->trial_ends_at && $this->trial_ends_at->isFuture()
                ? self::TRIAL
                : self::EXPIRED;
        }

        if (! $this->ends_at) {
            return self::EXPIRED;
        }

        if ($this->ends_at->isFuture()) {
            return self::ACTIVE;
        }

        $graceEnd = $this->ends_at->copy()->addDays((int) config('subscription.grace_days'));

        return $graceEnd->isFuture() ? self::PAST_DUE : self::EXPIRED;
    }

    public function isWritable(): bool
    {
        return in_array($this->currentStatus(), [self::TRIAL, self::ACTIVE, self::PAST_DUE], true);
    }

    public function daysLeft(): ?int
    {
        $end = $this->status === self::TRIAL ? $this->trial_ends_at : $this->ends_at;

        if (! $end) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInDays($end, false)));
    }

    public function statusLabel(): string
    {
        return match ($this->currentStatus()) {
            self::TRIAL => 'ট্রায়াল',
            self::ACTIVE => 'সক্রিয়',
            self::PAST_DUE => 'বকেয়া (grace)',
            self::EXPIRED => 'মেয়াদ শেষ',
            self::CANCELLED => 'বাতিল',
        };
    }

    public function badgeClass(): string
    {
        return match ($this->currentStatus()) {
            self::TRIAL => 'info',
            self::ACTIVE => 'success',
            self::PAST_DUE => 'warning',
            default => 'danger',
        };
    }

    public function endDate(): ?\Illuminate\Support\Carbon
    {
        return $this->status === self::TRIAL ? $this->trial_ends_at : $this->ends_at;
    }
}
