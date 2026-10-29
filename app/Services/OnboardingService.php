<?php

namespace App\Services;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    /**
     * @return array{
     *   visible: bool, restorable: bool, steps: array, done: int, total: int,
     *   percent: int, next: ?string, core_done: bool, all_done: bool
     * }
     */
    public function for(Farm $farm, User $user): array
    {
        $farm->loadMissing('subscription');

        // শুধু-দেখার মোডে কিছু করার উপায় নেই, তাই গাইড দেখানো অর্থহীন
        if (! $farm->subscription?->isWritable()) {
            return $this->hidden();
        }

        $dismissed = DB::table('farm_user')
            ->where('farm_id', $farm->id)
            ->where('user_id', $user->id)
            ->whereNotNull('onboarding_dismissed_at')
            ->exists();

        $hasCows = $farm->cows()->exists();
        $hasMilking = $farm->cows()->where('status', Cow::STATUS_ACTIVE)->where('category', 'milking')->exists();

        // কোন ধাপ শেষ: সব ডেটা থেকে নিজে নিজে বোঝা যায়
        $facts = [
            'cows' => $hasCows,
            'milk' => $farm->milkRecords()->exists(),
            'expense' => $farm->expenses()->where('status', 'active')->exists(),
            'sale' => $farm->sales()->where('status', 'active')->exists(),
            'health' => $farm->healthEvents()->exists(),
            'profile' => filled($farm->phone) && filled($farm->address),
            'members' => $farm->users()->count() > 1,
            'sms' => (bool) $farm->sms_enabled,
        ];

        $isOwner = $farm->roleOf($user) === Farm::ROLE_OWNER;
        $canFinance = $user->can('manageFinance', $farm);
        $canImport = $user->can('importCows', $farm);

        $definitions = [
            [
                'key' => 'cows',
                'core' => true,
                'icon' => 'bi-collection',
                'title' => 'খামারের গরুগুলো যোগ করুন',
                'hint' => 'সব হিসাব গরু দিয়ে শুরু। অনেক গরু থাকলে Excel থেকে একবারে তুলে নিন।',
                'label' => 'গরু যোগ করুন',
                'url' => route('farms.cows.create', $farm),
                'alt' => $canImport ? ['label' => 'Excel থেকে ইমপোর্ট', 'url' => route('farms.cows.import', $farm)] : null,
                'show' => $user->can('create', [Cow::class, $farm]),
            ],
            [
                'key' => 'milk',
                'core' => true,
                'icon' => 'bi-droplet-half',
                'requires' => 'cows',
                'title' => 'আজকের দুধ লিখুন',
                'hint' => 'প্রতিটি গাভির সকাল-বিকালের দুধ লিখলে উৎপাদন ও আয়ের হিসাব নিজে আসে।',
                'label' => 'দুধ লিখুন',
                'url' => route('farms.milk.entry', $farm),
                'alt' => null,
                // "দুধ দিচ্ছে" ধরনের গাভি না থাকলে এই ধাপ করার উপায়ই নেই, তাই লুকানো
                'show' => ! $hasCows || $hasMilking,
            ],
            [
                'key' => 'expense',
                'core' => true,
                'icon' => 'bi-receipt',
                'title' => 'প্রথম খরচ লিখুন',
                'hint' => 'খাবার, ওষুধ, শ্রমিকের খরচ লিখে রাখলে মাসের শেষে লাভ-ক্ষতি দেখা যায়।',
                'label' => 'খরচ লিখুন',
                'url' => route('farms.expenses.create', $farm),
                'alt' => null,
                'show' => $canFinance,
            ],
            [
                'key' => 'sale',
                'core' => true,
                'icon' => 'bi-cash-coin',
                'title' => 'প্রথম বিক্রি লিখুন',
                'hint' => 'দুধ, গোবর বা গরু বিক্রি লিখলে আয় ও বকেয়ার হিসাব রাখা যায়।',
                'label' => 'বিক্রি লিখুন',
                'url' => route('farms.sales.create', $farm),
                'alt' => null,
                'show' => $canFinance,
            ],
            [
                'key' => 'health',
                'core' => false,
                'icon' => 'bi-heart-pulse',
                'requires' => 'cows',
                'title' => 'টিকা বা চিকিৎসা লিখুন',
                'hint' => 'টিকা লিখলে পরের তারিখ কাছে এলে আগাম মনে করিয়ে দেওয়া হয়।',
                'label' => 'লিখুন',
                'url' => route('farms.health.create', $farm),
                'alt' => null,
                'show' => true,
            ],
            [
                'key' => 'profile',
                'core' => false,
                'icon' => 'bi-geo-alt',
                'title' => 'খামারের ফোন ও ঠিকানা দিন',
                'hint' => 'রিপোর্ট ও SMS বিজ্ঞপ্তিতে খামারের পরিচয় এখান থেকে আসে।',
                'label' => 'খামারের তথ্য',
                'url' => route('farms.edit', $farm),
                'alt' => null,
                'show' => $user->can('update', $farm),
            ],
            [
                'key' => 'members',
                'core' => false,
                'icon' => 'bi-people',
                'title' => 'সদস্য যোগ করুন',
                'hint' => 'ম্যানেজার বা স্টাফকে যোগ করুন, প্রত্যেকে নিজের ভূমিকা অনুযায়ী কাজ করবে।',
                'label' => 'সদস্য',
                'url' => route('farms.members.index', $farm),
                'alt' => null,
                'show' => $isOwner,
            ],
            [
                'key' => 'sms',
                'core' => false,
                'icon' => 'bi-chat-dots',
                'title' => 'SMS বিজ্ঞপ্তি চালু করুন',
                'hint' => 'টিকা, গর্ভ পরীক্ষা ও প্রসবের আগাম খবর আপনার মোবাইলে।',
                'label' => 'চালু করুন',
                'url' => route('farms.notifications.edit', $farm),
                'alt' => null,
                'show' => $isOwner,
            ],
        ];

        $steps = [];

        foreach ($definitions as $d) {
            if (! $d['show']) {
                continue;
            }

            $done = (bool) $facts[$d['key']];

            $steps[] = [
                'key' => $d['key'],
                'core' => $d['core'],
                'icon' => $d['icon'],
                'title' => $d['title'],
                'hint' => $d['hint'],
                'label' => $d['label'],
                'url' => $d['url'],
                'alt' => $d['alt'],
                'done' => $done,
                // গরু ছাড়া দুধ বা টিকা লেখা যায় না
                'locked' => ! $done && isset($d['requires']) && ! $facts[$d['requires']],
            ];
        }

        $total = count($steps);
        $done = count(array_filter($steps, fn($s) => $s['done']));
        $allDone = $total > 0 && $done === $total;
        $next = collect($steps)->first(fn($s) => ! $s['done'] && ! $s['locked'])['key'] ?? null;
        $coreDone = collect($steps)->where('core', true)->every(fn($s) => $s['done']);

        return [
            'visible' => $total > 0 && ! $allDone && ! $dismissed,
            'restorable' => $total > 0 && ! $allDone && $dismissed,
            'steps' => $steps,
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'next' => $next,
            'core_done' => $coreDone,
            'all_done' => $allDone,
        ];
    }

    public function dismiss(Farm $farm, User $user): void
    {
        $this->mark($farm, $user, now());
    }

    public function restore(Farm $farm, User $user): void
    {
        $this->mark($farm, $user, null);
    }

    private function mark(Farm $farm, User $user, mixed $value): void
    {
        DB::table('farm_user')
            ->where('farm_id', $farm->id)
            ->where('user_id', $user->id)
            ->update(['onboarding_dismissed_at' => $value]);
    }

    private function hidden(): array
    {
        return [
            'visible' => false,
            'restorable' => false,
            'steps' => [],
            'done' => 0,
            'total' => 0,
            'percent' => 0,
            'next' => null,
            'core_done' => false,
            'all_done' => false,
        ];
    }
}
