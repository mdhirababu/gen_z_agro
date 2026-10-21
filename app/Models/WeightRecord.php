<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeightRecord extends Model
{
    use BelongsToFarm, SoftDeletes;

    protected $fillable = ['weight_kg', 'measured_at', 'note'];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'measured_at' => 'date',
        ];
    }

    public function cow(): BelongsTo
    {
        return $this->belongsTo(Cow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
