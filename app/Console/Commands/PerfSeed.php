<?php

namespace App\Console\Commands;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PerfSeed extends Command
{
    protected $signature = 'perf:seed {--cows=300 : গরুর সংখ্যা} {--days=365 : কত দিনের ডেটা}';

    protected $description = 'পারফরম্যান্স পরীক্ষার জন্য নকল ডেটা বানায় (শুধু local, perf বা testing পরিবেশে)';

    public function handle(): int
    {
        if (! app()->environment('local', 'perf', 'testing')) {
            $this->error('এই কমান্ড শুধু local বা perf পরিবেশে চলে। প্রোডাকশন ডাটাবেসে কখনো নয়।');

            return self::FAILURE;
        }

        $cowCount = max(1, min(2000, (int) $this->option('cows')));
        $days = max(1, min(1500, (int) $this->option('days')));

        $user = User::factory()->create(['name' => 'Perf Owner']);
        $farm = Farm::factory()->create(['owner_user_id' => $user->id, 'name' => 'PERF খামার']);
        $farm->users()->attach($user->id, ['role' => 'owner']);
        app(SubscriptionService::class)->startTrial($farm);

        $cowIds = Cow::factory()
            ->count($cowCount)
            ->sequence(fn($sequence) => ['tag_number' => 'PF-' . str_pad((string) ($sequence->index + 1), 4, '0', STR_PAD_LEFT)])
            ->create(['farm_id' => $farm->id, 'category' => 'milking', 'sex' => 'female', 'status' => 'active'])
            ->pluck('id')
            ->all();

        $now = now()->toDateTimeString();
        $start = now()->subDays($days - 1)->startOfDay();
        $milk = [];
        $payments = [];
        $categories = ['feed', 'labor', 'medicine'];

        for ($d = 0; $d < $days; $d++) {
            $date = $start->copy()->addDays($d)->toDateString();

            // প্রতিটি গরুর দুধ
            foreach ($cowIds as $cowId) {
                $s1 = mt_rand(30, 90) / 10;
                $s2 = mt_rand(20, 70) / 10;

                $milk[] = [
                    'farm_id' => $farm->id,
                    'cow_id' => $cowId,
                    'milk_date' => $date,
                    'session_1' => $s1,
                    'session_2' => $s2,
                    'session_3' => 0,
                    'total_liters' => $s1 + $s2,
                    'source' => 'perf',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($milk) >= 500) {
                    DB::table('milk_records')->insert($milk);
                    $milk = [];
                }
            }

            // দিনে একটা দুধ বিক্রি (পুরো টাকা আদায়)
            $quantity = round($cowCount * 7.5, 2);
            $total = round($quantity * 60, 2);

            $saleId = DB::table('sales')->insertGetId([
                'farm_id' => $farm->id,
                'sale_type' => 'milk',
                'sold_at' => $date,
                'quantity' => $quantity,
                'unit' => 'liter',
                'unit_price' => 60,
                'total_amount' => $total,
                'paid_amount' => $total,
                'payment_status' => 'paid',
                'status' => 'active',
                'source' => 'perf',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $payments[] = [
                'farm_id' => $farm->id,
                'payable_type' => 'sale',
                'payable_id' => $saleId,
                'amount' => $total,
                'paid_at' => $date,
                'method' => 'cash',
                'status' => 'active',
                'source' => 'perf',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // দিনে তিনটা খরচ, প্রায় ৭০% পরিশোধিত
            foreach ($categories as $category) {
                $amount = mt_rand(500, 5000);
                $paid = mt_rand(1, 10) <= 7;

                $expenseId = DB::table('expenses')->insertGetId([
                    'farm_id' => $farm->id,
                    'cow_id' => null,
                    'category' => $category,
                    'expense_date' => $date,
                    'total_amount' => $amount,
                    'paid_amount' => $paid ? $amount : 0,
                    'payment_status' => $paid ? 'paid' : 'unpaid',
                    'status' => 'active',
                    'source' => 'perf',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($paid) {
                    $payments[] = [
                        'farm_id' => $farm->id,
                        'payable_type' => 'expense',
                        'payable_id' => $expenseId,
                        'amount' => $amount,
                        'paid_at' => $date,
                        'method' => 'cash',
                        'status' => 'active',
                        'source' => 'perf',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (count($payments) >= 500) {
                DB::table('payments')->insert($payments);
                $payments = [];
            }
        }

        if ($milk) {
            DB::table('milk_records')->insert($milk);
        }
        if ($payments) {
            DB::table('payments')->insert($payments);
        }

        $this->info("খামার id: {$farm->id} | গরু: {$cowCount} | দিন: {$days}");
        $this->line('এখন: php artisan perf:report ' . $farm->id);

        return self::SUCCESS;
    }
}
