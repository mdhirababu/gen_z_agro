<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AuditShow extends Command
{
    protected $signature = 'audit:show {--farm= : খামারের id} {--days=7 : কত দিন আগে থেকে} {--event= : created, updated, deleted, voided, exported ...} {--limit=50}';

    protected $description = 'সাম্প্রতিক অডিট লগ দেখায় (সুপার অ্যাডমিনের জন্য)';

    public function handle(): int
    {
        $logs = AuditLog::query()
            ->when($this->option('farm'), fn($q, $id) => $q->where('farm_id', $id))
            ->when($this->option('event'), fn($q, $event) => $q->where('event', $event))
            ->where('created_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))
            ->latest('id')
            ->limit(max(1, min(500, (int) $this->option('limit'))))
            ->get();

        if ($logs->isEmpty()) {
            $this->info('কোনো রেকর্ড নেই।');

            return self::SUCCESS;
        }

        $types = config('audit.types');
        $events = config('audit.events');

        $this->table(
            ['id', 'সময়', 'খামার', 'কে', 'কী হয়েছে', 'কিসের', 'বদল'],
            $logs->map(fn($l) => [
                $l->id,
                $l->created_at->format('d/m H:i'),
                $l->farm_id ?? '-',
                $l->actor_name ?: 'সিস্টেম',
                $events[$l->event] ?? $l->event,
                ($types[$l->auditable_type] ?? $l->auditable_type) . ($l->subject ? ': ' . $l->subject : ''),
                Str::limit(json_encode($l->new_values ?? [], JSON_UNESCAPED_UNICODE), 60),
            ])->all()
        );

        return self::SUCCESS;
    }
}
