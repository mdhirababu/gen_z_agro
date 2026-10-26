<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\FeedType;
use App\Models\MilkRecord;
use App\Models\Plan;
use App\Models\User;
use App\Services\ReportService;
use App\Services\SubscriptionService;
use App\Support\ReportPeriod;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CowReportTest extends TestCase
{
    use RefreshDatabase;

    /** ১০ দিনের নির্দিষ্ট সময়কাল (আজ ধরা হয়েছে ২৫ অক্টোবর) */
    private const Q = ['r' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PlanSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));
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

    private function cow(Farm $farm, string $tag, array $override = []): Cow
    {
        return Cow::factory()->create(array_merge([
            'farm_id' => $farm->id,
            'tag_number' => $tag,
            'acquisition_type' => 'purchased',
            'purchase_price' => 50000,
            'purchase_date' => '2026-09-01',
        ], $override));
    }

    private function sell(User $user, Farm $farm, array $override = []): void
    {
        $this->actingAs($user)->post(route('farms.sales.store', $farm), array_merge([
            'sale_type' => 'milk',
            'sold_at' => '2026-10-05',
            'quantity' => 20,
            'unit_price' => 60,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ], $override))->assertSessionHasNoErrors();
    }

    private function spend(User $user, Farm $farm, array $override = []): void
    {
        $this->actingAs($user)->post(route('farms.expenses.store', $farm), array_merge([
            'category' => 'labor',
            'expense_date' => '2026-10-03',
            'total_amount' => 1500,
            'pay_mode' => 'unpaid',
            'pay_method' => 'cash',
        ], $override))->assertSessionHasNoErrors();
    }

    private function milk(Farm $farm, Cow $cow, string $date, float $liters): void
    {
        (new MilkRecord())->forceFill([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'milk_date' => $date,
            'session_1' => $liters,
            'session_2' => 0,
            'session_3' => 0,
            'source' => 'manual',
        ])->save();
    }

    private function feedType(Farm $farm, float $price = 10): FeedType
    {
        $t = new FeedType(['name' => 'ভুসি', 'unit' => 'kg', 'price_per_unit' => $price, 'is_active' => true]);
        $t->forceFill(['farm_id' => $farm->id])->save();

        return $t;
    }

    private function open(User $user, Farm $farm, array $extra = [])
    {
        return $this->actingAs($user)->get(route('farms.reports.cows', [$farm] + self::Q + $extra));
    }

    private function row(array $report, string $tag): array
    {
        return collect($report['rows'])->first(fn($r) => $r['cow']->tag_number === $tag);
    }

    public function test_shared_costs_and_milk_income_are_split_by_cow_days_and_production(): void
    {
        [$owner, $farm] = $this->farmWith();
        $a = $this->cow($farm, 'COW-A');
        $b = $this->cow($farm, 'COW-B', ['purchase_price' => 30000, 'purchase_date' => '2026-10-06']);

        $this->spend($owner, $farm); // শ্রমিক ১৫০০, কোনো গরুর নামে নয়
        $this->spend($owner, $farm, ['category' => 'medicine', 'expense_date' => '2026-10-04', 'total_amount' => 300, 'cow_id' => $a->id]);
        $this->milk($farm, $a, '2026-10-05', 20);
        $this->milk($farm, $b, '2026-10-05', 10);
        $this->sell($owner, $farm, ['quantity' => 30, 'unit_price' => 100]); // দুধ বিক্রি ৩০০০

        $report = $this->open($owner, $farm)->assertOk()->viewData('report');

        $rowA = $this->row($report, 'COW-A');
        $rowB = $this->row($report, 'COW-B');

        $this->assertSame(10, $rowA['days']);
        $this->assertSame(5, $rowB['days']);

        $this->assertEqualsWithDelta(2000, $rowA['income_milk'], 0.01);
        $this->assertEqualsWithDelta(1000, $rowB['income_milk'], 0.01);

        $this->assertEqualsWithDelta(1000, $rowA['joint_other'], 0.01); // ১৫০০ × ১০/১৫
        $this->assertEqualsWithDelta(500, $rowB['joint_other'], 0.01);
        $this->assertEqualsWithDelta(300, $rowA['cost_direct'], 0.01);

        $this->assertEqualsWithDelta(700, $rowA['result'], 0.01);
        $this->assertEqualsWithDelta(500, $rowB['result'], 0.01);
        $this->assertEqualsWithDelta(1200, $report['check_net'], 0.01);
    }

    public function test_shared_feed_cost_follows_feed_records(): void
    {
        [$owner, $farm] = $this->farmWith();
        $a = $this->cow($farm, 'COW-A');
        $b = $this->cow($farm, 'COW-B', ['purchase_date' => '2026-10-06']);
        $type = $this->feedType($farm, 10);

        $this->spend($owner, $farm, ['category' => 'feed', 'total_amount' => 1000, 'expense_date' => '2026-10-02']);
        $this->spend($owner, $farm, ['category' => 'labor', 'total_amount' => 300]);

        $this->actingAs($owner)->post(route('farms.feed.store', $farm), [
            'fed_on' => '2026-10-05',
            'feed_type_id' => $type->id,
            'quantity' => 30,
            'target' => 'cow',
            'cow_id' => $a->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('farms.feed.store', $farm), [
            'fed_on' => '2026-10-07',
            'feed_type_id' => $type->id,
            'quantity' => 10,
            'target' => 'cow',
            'cow_id' => $b->id,
        ])->assertSessionHasNoErrors();

        $report = $this->open($owner, $farm)->viewData('report');

        // খাবারের মান: A=৩০০, B=১০০, তাই ১০০০ টাকা ৭৫০ : ২৫০
        $this->assertEqualsWithDelta(750, $this->row($report, 'COW-A')['joint_feed'], 0.01);
        $this->assertEqualsWithDelta(250, $this->row($report, 'COW-B')['joint_feed'], 0.01);
        // শ্রমিক গরু-দিনে: ১০ : ৫
        $this->assertEqualsWithDelta(200, $this->row($report, 'COW-A')['joint_other'], 0.01);
        $this->assertFalse($report['feed_fallback']);
    }

    public function test_feed_cost_falls_back_to_cow_days_without_feed_records(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->cow($farm, 'COW-A');
        $this->cow($farm, 'COW-B', ['purchase_date' => '2026-10-06']);

        $this->spend($owner, $farm, ['category' => 'feed', 'total_amount' => 1500, 'expense_date' => '2026-10-02']);

        $report = $this->open($owner, $farm)->viewData('report');

        $this->assertTrue($report['feed_fallback']);
        $this->assertEqualsWithDelta(1000, $this->row($report, 'COW-A')['joint_feed'], 0.01);
        $this->assertEqualsWithDelta(500, $this->row($report, 'COW-B')['joint_feed'], 0.01);
    }

    public function test_the_allocation_rule_can_be_changed_to_equal_split(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->cow($farm, 'COW-A');
        $this->cow($farm, 'COW-B', ['purchase_date' => '2026-10-06']);

        $this->spend($owner, $farm);

        config(['farm.allocation.other' => 'equal']);

        $report = $this->open($owner, $farm)->viewData('report');

        $this->assertEqualsWithDelta(750, $this->row($report, 'COW-A')['joint_other'], 0.01);
        $this->assertEqualsWithDelta(750, $this->row($report, 'COW-B')['joint_other'], 0.01);
    }

    public function test_sold_cow_result_includes_sale_price_and_base_value(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = $this->cow($farm, 'SOLD-1', ['purchase_price' => 80000]);

        $this->sell($owner, $farm, ['sale_type' => 'cow', 'cow_id' => $cow->id, 'total_amount' => 100000, 'quantity' => null, 'unit_price' => null]);

        $row = $this->row($this->open($owner, $farm)->viewData('report'), 'SOLD-1');

        $this->assertSame(5, $row['days']); // ১ থেকে ৫ অক্টোবর
        $this->assertEqualsWithDelta(100000, $row['income_sale'], 0.01);
        $this->assertEqualsWithDelta(80000, $row['cost_base'], 0.01);
        $this->assertEqualsWithDelta(20000, $row['result'], 0.01);
    }

    public function test_dead_cow_is_written_off_at_base_value(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->cow($farm, 'DEAD-1', ['purchase_price' => 50000, 'status' => 'dead', 'exit_date' => '2026-10-04']);

        $row = $this->row($this->open($owner, $farm)->viewData('report'), 'DEAD-1');

        $this->assertEqualsWithDelta(50000, $row['cost_base'], 0.01);
        $this->assertEqualsWithDelta(-50000, $row['result'], 0.01);
    }

    public function test_income_and_costs_without_any_cow_stay_unallocated(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->sell($owner, $farm, ['sale_type' => 'manure', 'quantity' => 10, 'unit_price' => 20]);
        $this->sell($owner, $farm, ['sale_type' => 'other_income', 'total_amount' => 500, 'description' => 'ভর্তুকি', 'quantity' => null, 'unit_price' => null]);
        $this->spend($owner, $farm, ['total_amount' => 300, 'expense_date' => '2026-10-05']);

        $report = $this->open($owner, $farm)->viewData('report');

        $this->assertCount(0, $report['rows']);
        $this->assertEqualsWithDelta(700, $report['unallocated']['income'], 0.01);
        $this->assertEqualsWithDelta(300, $report['unallocated']['expense'], 0.01);
        $this->assertEqualsWithDelta(400, $report['check_net'], 0.01);
    }

    public function test_cow_results_reconcile_with_the_profit_loss_net(): void
    {
        [$owner, $farm] = $this->farmWith();
        $a = $this->cow($farm, 'COW-A');
        $b = $this->cow($farm, 'COW-B', ['purchase_price' => 30000, 'purchase_date' => '2026-10-06']);
        $sold = $this->cow($farm, 'SOLD-1', ['purchase_price' => 80000]);
        $this->cow($farm, 'DEAD-1', ['purchase_price' => 20000, 'status' => 'dead', 'exit_date' => '2026-10-04']);
        $type = $this->feedType($farm, 12);

        $this->spend($owner, $farm);                                                           // শ্রমিক ১৫০০
        $this->spend($owner, $farm, ['category' => 'feed', 'total_amount' => 600, 'expense_date' => '2026-10-02']);
        $this->spend($owner, $farm, ['category' => 'medicine', 'total_amount' => 300, 'expense_date' => '2026-10-04', 'cow_id' => $a->id]);

        $this->actingAs($owner)->post(route('farms.feed.store', $farm), [
            'fed_on' => '2026-10-03',
            'feed_type_id' => $type->id,
            'quantity' => 12,
            'target' => 'all',
        ])->assertSessionHasNoErrors();

        $this->milk($farm, $a, '2026-10-05', 20);
        $this->milk($farm, $b, '2026-10-05', 10);
        $this->milk($farm, $sold, '2026-10-02', 5);

        $this->sell($owner, $farm, ['quantity' => 30, 'unit_price' => 100]);
        $this->sell($owner, $farm, ['sale_type' => 'manure', 'quantity' => 10, 'unit_price' => 20]);
        $this->sell($owner, $farm, ['sale_type' => 'other_income', 'total_amount' => 500, 'description' => 'ভর্তুকি', 'quantity' => null, 'unit_price' => null]);
        $this->sell($owner, $farm, ['sale_type' => 'cow', 'cow_id' => $sold->id, 'total_amount' => 100000, 'quantity' => null, 'unit_price' => null]);

        $report = $this->open($owner, $farm)->assertOk()->viewData('report');

        app()->forgetInstance('currentFarm'); // আগের request-এর খামার বাঁধা থাকলে সরাসরি service ডাকার আগে ছাড়াতে হয়
        $pl = app(ReportService::class)->profitLoss($farm, ReportPeriod::make('custom', '2026-10-01', '2026-10-10'));

        $this->assertEqualsWithDelta($pl['net'], $report['check_net'], 0.05);
    }

    public function test_status_filter_and_other_farms_data(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();

        $this->cow($farm, 'LIVE-1');
        $this->cow($farm, 'SOLD-1', ['status' => 'sold', 'exit_date' => '2026-10-05']);
        $this->cow($other, 'OTHER-1');

        $report = $this->open($owner, $farm)->viewData('report');
        $tags = collect($report['rows'])->map(fn($r) => $r['cow']->tag_number)->all();

        $this->assertContains('LIVE-1', $tags);
        $this->assertNotContains('OTHER-1', $tags);

        $rows = $this->open($owner, $farm, ['status' => 'sold'])->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('SOLD-1', $rows[0]['cow']->tag_number);
    }

    public function test_report_is_locked_when_the_plan_does_not_include_it(): void
    {
        [$owner, $farm] = $this->farmWith();

        Plan::where('slug', 'basic')->update(['features' => ['export' => true, 'cow_profit_report' => false]]);

        $this->open($owner, $farm)->assertOk()->assertSee('প্ল্যানে নেই');
    }

    public function test_staff_cannot_open_it_but_manager_and_expired_farm_can_view(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $this->open($staff, $farm)->assertForbidden();

        [$manager, $farm2] = $this->farmWith('manager');
        $farm2->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->open($manager, $farm2)->assertOk();
    }

    public function test_reports_hub_links_to_the_cow_report(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)
            ->get(route('farms.reports.index', $farm))
            ->assertOk()
            ->assertSee('গরুভিত্তিক রিপোর্ট দেখুন');
    }
}
