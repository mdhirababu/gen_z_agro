<?php

namespace App\Support;

use App\Models\Farm;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ReminderMessage
{
    /** @param Collection<int, FarmTask> $tasks */
    public static function build(Farm $farm, Collection $tasks): string
    {
        $today = $tasks->filter(fn(FarmTask $t) => $t->days() === 0)->count();
        $soon = $tasks->filter(fn(FarmTask $t) => $t->days() > 0)->count();
        $late = $tasks->filter(fn(FarmTask $t) => $t->days() < 0)->count();

        $parts = [];
        if ($today) {
            $parts[] = "আজ {$today}টি";
        }
        if ($soon) {
            $parts[] = "৩ দিনের মধ্যে {$soon}টি";
        }
        if ($late) {
            $parts[] = "দেরি {$late}টি";
        }

        $head = 'Gen Z Agro, ' . Str::limit($farm->name, 25, '') . ': ' . implode(', ', $parts) . ' কাজ বাকি।';

        $groups = $tasks
            ->groupBy('title')
            ->map(fn($group, $title) => $title . ': ' . $group->map(fn(FarmTask $t) => $t->cow->tag_number)->unique()->implode(', '))
            ->implode('। ');

        $text = trim($head . ' ' . $groups . '।');

        // বেশি লম্বা হলে খরচ বাড়ে, তাই ২০০ অক্ষরে কাটা
        return mb_strlen($text) > 200 ? mb_substr($text, 0, 197) . '...' : $text;
    }
}
