<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedRecord extends Model
{
    use BelongsToFarm, SoftDeletes;

    // farm_id, feed_type_id, unit, target, category, cow_id, head_count, created_by, source: server বসাবে
    protected $fillable = ['fed_on', 'quantity', 'note'];

    protected function casts(): array
    {
        return [
            'fed_on' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class)->withTrashed();
    }

    public function cow(): BelongsTo
    {
        return $this->belongsTo(Cow::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function unitLabel(): string
    {
        return config('farm.feed_units')[$this->unit] ?? $this->unit;
    }

    public function targetLabel(): string
    {
        return match ($this->target) {
            'category' => 'ধরন: ' . (config('farm.categories')[$this->category]['label'] ?? $this->category),
            'cow' => 'গরু: ' . ($this->cow ? $this->cow->tag_number : '—'),
            default => 'পুরো পাল',
        };
    }

    /** প্রতি গরুতে কত */
    public function perHead(): float
    {
        return $this->head_count > 0 ? round((float) $this->quantity / $this->head_count, 2) : 0.0;
    }
}
