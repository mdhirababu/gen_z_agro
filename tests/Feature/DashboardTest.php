<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\MilkRecord;
use App\Models\Sale;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PlanSeeder::class);
    }

    private function farmWith(string $role = 'owner'): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['owner_user_id' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        app(SubscriptionService::class)->startTrial($farm);

        if ($role === 'owner') {
            return [$owner, $farm->fresh()];
        }

        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => $role]);

        return [$member, $farm->fresh()];
    }

    private function milk(Farm $farm, Cow $cow, float $liters): void
    {
        (new MilkRecord())->forceFill([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'milk_date' => now()->toDateString(),
            'session_1' => $liters,
            'session_2' => 0,
            'session_3' => 0,
            'source' => 'manual',
        ])->save();
    }

    private function sale(Farm $farm, float $total, float $paid = 0, string $status = 'active'): void
    {
        $sale = new Sale(['sale_type' => 'other_income', 'sold_at' => now()->toDateString(), 'description' => 'নমুনা']);
        $sale->forceFill([
            'farm_id' => $farm->id,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'payment_status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            'status' => $status,
            'source' => 'manual',
        ])->save();
    }

    private function expense(Farm $farm, float $total, float $paid = 0): void
    {
        $e = new Expense(['category' => 'feed', 'expense_date' => now()->toDateString(), 'description' => 'নমুনা']);
        $e->forceFill([
            'farm_id' => $farm->id,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'payment_status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
            'status' => 'active',
            'source' => 'manual',
        ])->save();
    }

    public function test_owner_sees_real_numbers(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        Cow::factory()->create(['farm_id' => $farm->id]);
        Cow::factory()->create(['farm_id' => $farm->id, 'category' => 'calf']);

        $this->milk($farm, $cow, 30);
        $this->sale($farm, 1200, 1200);
        $this->sale($farm, 3000, 1000);
        $this->expense($farm, 5000);

        $this->actingAs($owner)
            ->get(route('farms.dashboard', $farm))
            ->assertOk()
            ->assertViewHas('cowTotal', 3)
            ->assertViewHas('milkToday', fn($v) => abs($v - 30.0) < 0.01)
            ->assertViewHas('monthSales', fn($v) => abs($v - 4200.0) < 0.01)
            ->assertViewHas('monthExpenses', fn($v) => abs($v - 5000.0) < 0.01)
            ->assertViewHas('receivable', fn($v) => abs($v - 2000.0) < 0.01)
            ->assertViewHas('payable', fn($v) => abs($v - 5000.0) < 0.01);
    }

    public function test_void_records_and_other_farms_are_not_counted(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();

        $this->sale($farm, 1000, 1000);
        $this->sale($farm, 9999, 0, 'void');
        $this->sale($other, 7777, 7777);

        $this->actingAs($owner)
            ->get(route('farms.dashboard', $farm))
            ->assertViewHas('monthSales', fn($v) => abs($v - 1000.0) < 0.01);
    }

    public function test_staff_does_not_get_money_figures(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $this->sale($farm, 1000, 0);

        $this->actingAs($staff)
            ->get(route('farms.dashboard', $farm))
            ->assertOk()
            ->assertViewHas('canFinance', false)
            ->assertViewHas('monthSales', 0.0)
            ->assertDontSee('মোট পাওনা');
    }

    public function test_empty_farm_dashboard_renders(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)
            ->get(route('farms.dashboard', $farm))
            ->assertOk()
            ->assertSee('এখনো কোনো গরু যোগ করা হয়নি');
    }
}
