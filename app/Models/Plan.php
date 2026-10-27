<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\Auditable;

class Plan extends Model
{
    use Auditable;
    protected $fillable = [
        'name',
        'slug',
        'price',
        'duration_days',
        'max_cows',
        'max_members',
        'features',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function allows(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }
}
