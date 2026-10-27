<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class AuditController extends Controller
{
    public function index(Request $request, Farm $farm)
    {
        Gate::authorize('viewAudit', $farm);

        $from = $this->date($request->query('from')) ?? now()->subDays(29)->toDateString();
        $to = $this->date($request->query('to')) ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $type = $request->query('type');
        $type = is_string($type) && array_key_exists($type, config('audit.types')) ? $type : null;

        $event = $request->query('event');
        $event = is_string($event) && array_key_exists($event, config('audit.events')) ? $event : null;

        $userId = $request->query('user');
        $userId = is_string($userId) && ctype_digit($userId) ? (int) $userId : null;

        $logs = AuditLog::query()
            ->where('farm_id', $farm->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($type, fn($q) => $q->where('auditable_type', $type))
            ->when($event, fn($q) => $q->where('event', $event))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        // ফিল্টারের জন্য: এই খামারে যারা কাজ করেছে
        $actors = AuditLog::query()
            ->where('farm_id', $farm->id)
            ->whereNotNull('user_id')
            ->select('user_id', 'actor_name')
            ->distinct()
            ->orderBy('actor_name')
            ->get();

        return view('audit.index', [
            'farm' => $farm,
            'logs' => $logs,
            'actors' => $actors,
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'event' => $event,
            'userId' => $userId,
        ]);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
