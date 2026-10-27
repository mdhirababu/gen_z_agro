<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\Auditable;

class Sale extends Model
{
    use BelongsToFarm, Payable, Auditable;

    protected array $auditIgnore = ['paid_amount', 'payment_status'];
    protected bool $auditVoidsOnStatus = true;
    // টাকার অঙ্ক, অবস্থা, farm_id, created_by এখানে নেই: শুধু service বসাবে
    protected $fillable = [
        'sale_type',
        'customer_id',
        'cow_id',
        'sold_at',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function cow(): BelongsTo
    {
        return $this->belongsTo(Cow::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function typeMeta(): array
    {
        return config('farm.sale_types')[$this->sale_type]
            ?? ['label' => $this->sale_type, 'unit' => null, 'icon' => 'bi-cash', 'tone' => 'sand', 'color' => '#8a9c93'];
    }
}
