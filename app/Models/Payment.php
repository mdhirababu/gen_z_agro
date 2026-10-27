<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Concerns\Auditable;

class Payment extends Model
{
    use BelongsToFarm, Auditable;

    protected bool $auditVoidsOnStatus = true;
    protected $fillable = ['amount', 'paid_at', 'method', 'note'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function methodLabel(): string
    {
        return config('farm.payment_methods')[$this->method] ?? $this->method;
    }
}
