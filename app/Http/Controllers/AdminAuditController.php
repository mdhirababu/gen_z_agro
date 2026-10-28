<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $from = $this->date($request->query('from')) ?? now()->subDays(6)->toDateString();
        $to = $this->date($request->query('to')) ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $type = $request->query('type');
        $type = is_string($type) && array_key_exists($type, config('audit.types')) ? $type : null;

        $event = $request->query('event');
        $event = is_string($event) && array_key_exists($event, config('audit.events')) ? $event : null;

        $farmId = $request->query('farm');
        $farmId = is_string($farmId) && ctype_digit($farmId) ? (int) $farmId : null;

        $logs = AuditLog::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($farmId, fn($q) => $q->where('farm_id', $farmId))
            ->when($type, fn($q) => $q->where('auditable_type', $type))
            ->when($event, fn($q) => $q->where('event', $event))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        $farms = Farm::withTrashed()
            ->whereIn('id', $logs->pluck('farm_id')->filter()->unique())
            ->pluck('name', 'id');

        return view('admin.audit', compact('logs', 'farms', 'from', 'to', 'type', 'event', 'farmId'));
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
