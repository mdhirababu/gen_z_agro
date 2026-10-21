<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        if (! in_array($status, [
            SubscriptionPayment::PENDING,
            SubscriptionPayment::APPROVED,
            SubscriptionPayment::REJECTED,
        ], true)) {
            $status = null;
        }

        $payments = SubscriptionPayment::with(['farm', 'plan', 'reviewer'])
            ->when($status, fn($query) => $query->where('status', $status))
            ->orderByRaw("status = 'pending' desc")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // অবস্থা অনুযায়ী সংখ্যা ও মোট টাকা (ওপরের কার্ড ও chip-এর জন্য)
        $summary = SubscriptionPayment::query()
            ->selectRaw('status, count(*) as total, sum(amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('admin.payments', compact('payments', 'status', 'summary'));
    }

    public function approve(SubscriptionPayment $payment, SubscriptionService $service)
    {
        $done = $service->approve($payment, auth()->user());

        return back()->with(
            $done ? 'success' : 'error',
            $done ? 'পেমেন্ট অনুমোদিত, সাবস্ক্রিপশন চালু হয়েছে।' : 'এই পেমেন্ট আগেই প্রক্রিয়া করা হয়েছে।'
        );
    }

    public function reject(Request $request, SubscriptionPayment $payment, SubscriptionService $service)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        $done = $service->reject($payment, auth()->user(), $note);

        return back()->with(
            $done ? 'success' : 'error',
            $done ? 'পেমেন্ট বাতিল করা হয়েছে।' : 'এই পেমেন্ট আগেই প্রক্রিয়া করা হয়েছে।'
        );
    }
}
