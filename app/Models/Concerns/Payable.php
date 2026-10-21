<?php

namespace App\Models\Concerns;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Payable
{
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /** বাকি টাকা। বাতিল রেকর্ডে সবসময় ০ */
    public function dueAmount(): float
    {
        if ($this->isVoid()) {
            return 0.0;
        }

        return max(0.0, round((float) $this->total_amount - (float) $this->paid_amount, 2));
    }

    public function paymentLabel(): string
    {
        return config('farm.payment_statuses')[$this->payment_status] ?? $this->payment_status;
    }

    public function paymentPill(): string
    {
        return match ($this->payment_status) {
            'paid' => 'green',
            'partial' => 'orange',
            default => 'red',
        };
    }
}
