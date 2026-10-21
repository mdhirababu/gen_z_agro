<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToFarm, Payable;

    // টাকার অঙ্ক, অবস্থা, farm_id, created_by এখানে নেই: শুধু service বসাবে
    protected $fillable = [
        'cow_id',
        'category',
        'expense_date',
        'description',
        'vendor_name',
        'quantity',
        'unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'quantity' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
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

    public function categoryMeta(): array
    {
        return config('farm.expense_categories')[$this->category]
            ?? ['label' => $this->category, 'icon' => 'bi-receipt', 'tone' => 'sand', 'color' => '#8a9c93'];
    }
}
