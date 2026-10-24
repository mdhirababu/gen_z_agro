<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedType extends Model
{
    use BelongsToFarm, SoftDeletes;

    protected $fillable = ['name', 'unit', 'price_per_unit', 'is_active'];

    protected function casts(): array
    {
        return [
            'price_per_unit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(FeedRecord::class);
    }

    public function unitLabel(): string
    {
        return config('farm.feed_units')[$this->unit] ?? $this->unit;
    }
}
