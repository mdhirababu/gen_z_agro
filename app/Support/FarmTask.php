<?php

namespace App\Support;

use App\Models\Cow;
use Illuminate\Support\Carbon;

final class FarmTask
{
    public function __construct(
        public string $type,          // health, breeding_check, breeding_calving
        public Cow $cow,
        public string $title,
        public string $icon,
        public string $tone,
        public Carbon $date,
        public string $actionLabel,
        public string $actionUrl,
        public ?string $doneUrl = null,
    ) {}

    /** আজ থেকে কত দিন পরে (ঋণাত্মক হলে দেরি) */
    public function days(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->date->copy()->startOfDay(), false);
    }

    public function isOverdue(): bool
    {
        return $this->days() < 0;
    }
}
