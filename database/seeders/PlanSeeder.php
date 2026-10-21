<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['slug' => 'basic'], [
            'name' => 'Basic',
            'price' => 500,
            'duration_days' => 30,
            'max_cows' => 50,
            'max_members' => 3,
            'features' => ['export' => true, 'cow_profit_report' => true],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::updateOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'price' => 1000,
            'duration_days' => 30,
            'max_cows' => null, // সীমাহীন
            'max_members' => 10,
            'features' => ['export' => true, 'cow_profit_report' => true],
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
