<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $farms = Farm::with(['owner', 'subscription.plan'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhereHas('owner', function ($o) use ($q) {
                            $o->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // তারিখ থেকে হিসাব করা আসল অবস্থা (scheduler পিছিয়ে থাকলেও ভুল হবে না)
        $byStatus = Subscription::whereHas('farm')->get()
            ->groupBy(fn(Subscription $s) => $s->currentStatus())
            ->map->count();

        $stats = [
            'farms' => Farm::count(),
            'active' => $byStatus->get('active', 0),
            'trial' => $byStatus->get('trial', 0),
            'attention' => $byStatus->get('expired', 0) + $byStatus->get('past_due', 0),
        ];

        return view('admin.index', [
            'farms' => $farms,
            'q' => $q,
            'stats' => $stats,
            'pendingCount' => SubscriptionPayment::where('status', SubscriptionPayment::PENDING)->count(),
        ]);
    }
}
