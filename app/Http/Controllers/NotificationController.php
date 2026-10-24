<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function edit(Farm $farm, SmsService $sms)
    {
        Gate::authorize('manageNotifications', $farm);

        return view('notifications.edit', [
            'farm' => $farm,
            'logs' => SmsLog::where('farm_id', $farm->id)->latest('id')->limit(10)->get(),
            'used' => $sms->usedSegments($farm),
            'limit' => (int) config('sms.monthly_segment_limit'),
            'driver' => config('sms.driver'),
        ]);
    }

    public function update(Request $request, Farm $farm)
    {
        Gate::authorize('manageNotifications', $farm);

        $data = $request->validate([
            'sms_phone' => ['nullable', 'string', 'max:30', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
        ], [
            'sms_phone.regex' => 'সঠিক মোবাইল নম্বর দিন, যেমন 01712345678।',
        ], ['sms_phone' => 'SMS-এর নম্বর']);

        $enabled = $request->boolean('sms_enabled');
        $phone = $data['sms_phone'] ?? null;

        if ($enabled && ! SmsService::normalize($phone ?: $farm->phone)) {
            throw ValidationException::withMessages([
                'sms_phone' => 'SMS চালু করতে একটা সঠিক মোবাইল নম্বর দিতে হবে (খামারের ফোন নম্বরও সঠিক নয়)।',
            ]);
        }

        $farm->forceFill([
            'sms_enabled' => $enabled,
            'sms_phone' => $phone ? SmsService::local($phone) : null,
        ])->save();

        return back()->with('success', 'বিজ্ঞপ্তির সেটিং সংরক্ষণ হয়েছে।');
    }

    public function test(Farm $farm, SmsService $sms)
    {
        Gate::authorize('manageNotifications', $farm);

        $number = $farm->sms_phone ?: $farm->phone;

        if (! SmsService::normalize($number)) {
            return back()->with('error', 'আগে একটা সঠিক মোবাইল নম্বর সংরক্ষণ করুন।');
        }

        $todayTests = SmsLog::where('farm_id', $farm->id)->where('kind', 'test')->whereDate('created_at', now()->toDateString())->count();

        if ($todayTests >= 3) {
            return back()->with('error', 'আজকের জন্য পরীক্ষামূলক SMS-এর সীমা (৩টি) শেষ।');
        }

        $log = $sms->sendTest($farm, $number);

        return back()->with(
            $log->status === 'sent' ? 'success' : 'error',
            $log->status === 'sent'
                ? (config('sms.driver') === 'log' ? 'পরীক্ষা সফল। (log driver, আসলে SMS যায়নি, লগ ফাইলে লেখা হয়েছে।)' : 'পরীক্ষামূলক SMS পাঠানো হয়েছে।')
                : 'SMS পাঠানো যায়নি: ' . ($log->error ?: 'অজানা কারণ')
        );
    }
}
