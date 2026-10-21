<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    // status, reviewed_by, reviewed_at ইচ্ছে করে fillable-এ নেই (শুধু service বদলাবে)
    protected $fillable = [
        'farm_id',
        'subscription_id',
        'plan_id',
        'amount',
        'method',
        'trx_id',
        'sender_number',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            default => 'warning',
        };
    }
}
