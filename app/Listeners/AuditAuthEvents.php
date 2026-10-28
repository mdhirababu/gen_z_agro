<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuditAuthEvents
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Failed::class => 'onFailed',
            Lockout::class => 'onLockout',
            PasswordReset::class => 'onPasswordReset',
        ];
    }

    public function onLogin(Login $event): void
    {
        $this->forFarms('login', $event->user, 'লগইন');
    }

    public function onFailed(Failed $event): void
    {
        // অজানা ইমেইলের ভুল চেষ্টা লগে ভরা হয় না: রেট লিমিটই যথেষ্ট, আর আক্রমণে লগ ফুলে যেত
        if (! $event->user) {
            return;
        }

        // একই ব্যবহারকারীর জন্য ৫ মিনিটে একবার
        if (! Cache::add('audit:login-failed:' . $event->user->getAuthIdentifier(), 1, now()->addMinutes(5))) {
            return;
        }

        $this->forFarms('login_failed', $event->user, 'ভুল পাসওয়ার্ডে লগইনের চেষ্টা');
    }

    public function onLockout(Lockout $event): void
    {
        if (! Cache::add('audit:lockout:' . $event->request->ip(), 1, now()->addMinutes(10))) {
            return;
        }

        $email = strtolower(trim((string) $event->request->input('email')));
        $user = $email !== '' ? User::query()->where('email', $email)->first() : null;

        if ($user) {
            $this->forFarms('lockout', $user, 'অনেকবার ভুল চেষ্টায় লগইন আটকানো হয়েছে');

            return;
        }

        Audit::log('lockout', null, 'User', null, 'অনেকবার ভুল চেষ্টায় লগইন আটকানো হয়েছে');
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->forFarms('password_reset', $event->user, 'পাসওয়ার্ড রিসেট');
    }

    /** ব্যবহারকারী যে যে খামারের সদস্য, প্রতিটির মালিক যেন দেখতে পান */
    private function forFarms(string $event, $user, string $label): void
    {
        $farmIds = DB::table('farm_user')
            ->where('user_id', $user->getAuthIdentifier())
            ->limit(5)
            ->pluck('farm_id')
            ->all();

        foreach ($farmIds ?: [null] as $farmId) {
            Audit::log(
                $event,
                $farmId ? (int) $farmId : null,
                'User',
                (int) $user->getAuthIdentifier(),
                $label . ': ' . $user->name,
            );
        }
    }
}
