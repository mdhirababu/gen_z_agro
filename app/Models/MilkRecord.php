<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilkRecord extends Model
{
    use BelongsToFarm;

    protected $fillable = ['session_1', 'session_2', 'session_3', 'note'];

    protected function casts(): array
    {
        return [
            'milk_date' => 'date',
            'session_1' => 'decimal:2',
            'session_2' => 'decimal:2',
            'session_3' => 'decimal:2',
            'total_liters' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // মোট সবসময় তিন বারের যোগফল, হাতে লেখা মান ধরা হয় না
        static::saving(function (MilkRecord $record) {
            $record->total_liters = round(
                (float) $record->session_1 + (float) $record->session_2 + (float) $record->session_3,
                2
            );
        });
    }

    public function cow(): BelongsTo
    {
        return $this->belongsTo(Cow::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
