<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Customer;
use App\Models\Farm;
use App\Models\MilkRecord;
use App\Models\Sale;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\ReportPeriod;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
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

    private function customer(Farm $farm): Customer
    {
        $c = new Customer(['name' => 'রহিম']);
        $c->forceFill(['farm_id' => $farm->id])->save();

        return $c;
    }

    private function sell(User $user, Farm $farm, array $override = []): void
    {
        $this->actingAs($user)->post(route('farms.sales.store', $farm), array_merge([
            'sale_type' => 'milk',
            'sold_at' => now()->toDateString(),
            'quantity' => 20,
            'unit_price' => 60,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ], $override))->assertSessionHasNoErrors();
    }

    private function spend(User $user, Farm $farm, array $override = []): void
    {
        $this->actingAs($user)->post(route('farms.expenses.store', $farm), array_merge([
            'category' => 'feed',
            'expense_date' => now()->toDateString(),
            'total_amount' => 5000,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ], $override))->assertSessionHasNoErrors();
    }

    private function report(User $user, Farm $farm, string $route, array $query = [])
    {
        return $this->actingAs($user)->get(route($route, [$farm] + $query));
    }

    public function test_period_presets(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00')); // রবিবার

        $p = ReportPeriod::make('this_month');
        $this->assertSame('2026-10-01', $p->fromDate());
        $this->assertSame('2026-10-25', $p->toDate());

        $p = ReportPeriod::make('last_month');
        $this->assertSame('2026-09-01', $p->fromDate());
        $this->assertSame('2026-09-30', $p->toDate());

        $this->assertSame('2026-10-24', ReportPeriod::make('this_week')->fromDate()); // শনিবার থেকে
        $this->assertSame('2026-01-01', ReportPeriod::make('this_year')->fromDate());
        $this->assertSame('2026-07-01', ReportPeriod::make('fiscal_year')->fromDate());
        $this->assertSame('2026-10-25', ReportPeriod::make('today')->fromDate());

        $this->travelTo(Carbon::parse('2026-03-10 10:00:00'));
        $this->assertSame('2025-07-01', ReportPeriod::make('fiscal_year')->fromDate());
    }

    public function test_invalid_custom_range_falls_back_to_this_month(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));

        $reversed = ReportPeriod::make('custom', '2026-10-10', '2026-10-01');
        $future = ReportPeriod::make('custom', '2026-10-01', '2026-11-30');
        $garbage = ReportPeriod::make('custom', 'abc', null);
        $valid = ReportPeriod::make('custom', '2026-10-01', '2026-10-10');

        foreach ([$reversed, $future, $garbage] as $p) {
            $this->assertSame('this_month', $p->key);
            $this->assertNotNull($p->notice);
        }

        $this->assertSame('custom', $valid->key);
        $this->assertNull($valid->notice);
        $this->assertSame('2026-10-10', $valid->toDate());
    }

    public function test_cash_flow_counts_only_real_payments_in_the_period(): void
    {
        [$owner, $farm] = $this->farmWith();
        $customer = $this->customer($farm);

        $this->sell($owner, $farm); // নগদ ১২০০
        $this->sell($owner, $farm, ['pay_mode' => 'unpaid', 'customer_id' => $customer->id, 'quantity' => 10, 'unit_price' => 100]); // বকেয়া ১০০০

        $credit = Sale::withoutGlobalScopes()->where('payment_status', 'unpaid')->firstOrFail();
        $this->actingAs($owner)->post(route('farms.sales.payments.store', [$farm, $credit]), [
            'amount' => 400,
            'paid_at' => now()->toDateString(),
            'method' => 'bkash',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('farms.sales.payments.store', [$farm, $credit]), [
            'amount' => 100,
            'paid_at' => now()->subDays(40)->toDateString(),
            'method' => 'cash', // সীমার বাইরে
        ])->assertSessionHasNoErrors();

        $this->spend($owner, $farm); // নগদ ৫০০০
        $this->spend($owner, $farm, ['pay_mode' => 'unpaid', 'total_amount' => 2000]); // বকেয়া, নগদে নেই

        $this->report($owner, $farm, 'farms.reports.cash-flow', ['r' => 'today'])
            ->assertOk()
            ->assertViewHas('report', fn($r) => abs($r['in_total'] - 1600) < 0.01
                && abs($r['out_total'] - 5000) < 0.01
                && abs($r['net'] + 3400) < 0.01);
    }

    public function test_cash_flow_ignores_voided_records_and_other_farms(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$otherOwner, $other] = $this->farmWith();

        $this->spend($owner, $farm, ['total_amount' => 3000]);
        $expense = \App\Models\Expense::withoutGlobalScopes()->where('farm_id', $farm->id)->firstOrFail();
        $this->actingAs($owner)->post(route('farms.expenses.void', [$farm, $expense]), ['reason' => 'ভুল খরচ'])->assertSessionHas('success');

        $this->sell($otherOwner, $other, ['quantity' => 10, 'unit_price' => 70]);

        $this->report($owner, $farm, 'farms.reports.cash-flow', ['r' => 'today'])
            ->assertViewHas('report', fn($r) => $r['in_total'] == 0.0 && $r['out_total'] == 0.0);
    }

    public function test_cow_purchases_are_cash_out_in_their_period(): void
    {
        [$owner, $farm] = $this->farmWith();

        Cow::factory()->create(['farm_id' => $farm->id, 'acquisition_type' => 'purchased', 'purchase_price' => 80000, 'purchase_date' => now()->toDateString()]);
        Cow::factory()->create(['farm_id' => $farm->id, 'acquisition_type' => 'purchased', 'purchase_price' => 50000, 'purchase_date' => now()->subMonths(3)->toDateString()]);
        Cow::factory()->create(['farm_id' => $farm->id, 'acquisition_type' => 'existing', 'purchase_price' => 0, 'opening_value' => 30000, 'purchase_date' => null]);
        Cow::factory()->create(['farm_id' => $farm->id, 'acquisition_type' => 'born', 'purchase_price' => 0, 'purchase_date' => null]);

        $this->report($owner, $farm, 'farms.reports.cash-flow', ['r' => 'today'])
            ->assertViewHas('report', function ($r) {
                $line = collect($r['out_by_category'])->firstWhere('key', 'cow_purchase');

                return abs($r['out_total'] - 80000) < 0.01 && $line && abs($line['total'] - 80000) < 0.01;
            });
    }

    public function test_profit_loss_counts_unpaid_sales_and_expenses(): void
    {
        [$owner, $farm] = $this->farmWith();
        $customer = $this->customer($farm);

        $this->sell($owner, $farm); // ১২০০ নগদ
        $this->sell($owner, $farm, ['pay_mode' => 'unpaid', 'customer_id' => $customer->id, 'quantity' => 10, 'unit_price' => 100]); // ১০০০ বকেয়া
        $this->spend($owner, $farm, ['pay_mode' => 'unpaid']); // ৫০০০ বকেয়া

        $this->report($owner, $farm, 'farms.reports.profit-loss', ['r' => 'today'])
            ->assertOk()
            ->assertViewHas('report', fn($r) => abs($r['revenue']['milk'] - 2200) < 0.01
                && abs($r['operating_expense'] - 5000) < 0.01
                && abs($r['operating_result'] + 2800) < 0.01
                && abs($r['net'] + 2800) < 0.01);
    }

    public function test_cow_sale_result_uses_the_base_value_of_the_cow(): void
    {
        [$owner, $farm] = $this->farmWith();
        $bought = Cow::factory()->create(['farm_id' => $farm->id, 'acquisition_type' => 'purchased', 'purchase_price' => 80000, 'tag_number' => 'BOUGHT']);
        $born = Cow::factory()->create(['farm_id' => $farm->id, 'acquisition_type' => 'born', 'purchase_price' => 0, 'tag_number' => 'BORN']);

        $this->sell($owner, $farm, ['sale_type' => 'cow', 'cow_id' => $bought->id, 'total_amount' => 100000, 'quantity' => null, 'unit_price' => null]);
        $this->sell($owner, $farm, ['sale_type' => 'cow', 'cow_id' => $born->id, 'total_amount' => 30000, 'quantity' => null, 'unit_price' => null]);

        $this->report($owner, $farm, 'farms.reports.profit-loss', ['r' => 'today'])
            ->assertViewHas('report', function ($r) {
                $bought = collect($r['cow_lines'])->firstWhere('tag', 'BOUGHT');
                $born = collect($r['cow_lines'])->firstWhere('tag', 'BORN');

                return abs($r['cow_sales_total'] - 130000) < 0.01
                    && abs($r['cow_base_total'] - 80000) < 0.01
                    && abs($r['cow_result'] - 50000) < 0.01
                    && abs($bought['result'] - 20000) < 0.01
                    && abs($born['result'] - 30000) < 0.01
                    && abs($r['net'] - 50000) < 0.01;
            });
    }

    public function test_dead_and_transferred_cows_are_written_off_at_base_value(): void
    {
        [$owner, $farm] = $this->farmWith();

        Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'dead', 'exit_date' => now()->toDateString(), 'acquisition_type' => 'purchased', 'purchase_price' => 50000]);
        Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'dead', 'exit_date' => now()->toDateString(), 'acquisition_type' => 'born', 'purchase_price' => 0]);
        Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'transferred', 'exit_date' => now()->toDateString(), 'acquisition_type' => 'existing', 'purchase_price' => 0, 'opening_value' => 30000]);
        Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'dead', 'exit_date' => now()->subMonths(3)->toDateString(), 'acquisition_type' => 'purchased', 'purchase_price' => 99999]);

        $this->report($owner, $farm, 'farms.reports.profit-loss', ['r' => 'today'])
            ->assertViewHas('report', fn($r) => abs($r['write_offs']['dead'] - 50000) < 0.01
                && abs($r['write_offs']['transferred'] - 30000) < 0.01
                && abs($r['write_offs']['total'] - 80000) < 0.01
                && abs($r['net'] + 80000) < 0.01);
    }

    public function test_voided_sales_and_expenses_are_not_counted(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->sell($owner, $farm);
        $this->spend($owner, $farm);

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $expense = \App\Models\Expense::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($owner)->post(route('farms.sales.void', [$farm, $sale]), ['reason' => 'ভুল বিক্রি']);
        $this->actingAs($owner)->post(route('farms.expenses.void', [$farm, $expense]), ['reason' => 'ভুল খরচ']);

        $this->report($owner, $farm, 'farms.reports.profit-loss', ['r' => 'today'])
            ->assertViewHas('report', fn($r) => $r['operating_income'] == 0.0 && $r['operating_expense'] == 0.0 && $r['net'] == 0.0);
    }

    public function test_milk_production_versus_sales(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        (new MilkRecord())->forceFill([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'milk_date' => now()->toDateString(),
            'session_1' => 30,
            'session_2' => 0,
            'session_3' => 0,
            'source' => 'manual',
        ])->save();

        $this->sell($owner, $farm); // ২০ লিটার, ৬০ টাকা, ১২০০ টাকা

        $this->report($owner, $farm, 'farms.reports.profit-loss', ['r' => 'today'])
            ->assertViewHas('report', fn($r) => abs($r['milk']['produced'] - 30) < 0.01
                && abs($r['milk']['sold'] - 20) < 0.01
                && abs($r['milk']['avg_price'] - 60) < 0.01);
    }

    public function test_other_farms_data_is_never_included(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$otherOwner, $other] = $this->farmWith();

        $this->sell($otherOwner, $other);
        $this->spend($otherOwner, $other);

        $this->report($owner, $farm, 'farms.reports.profit-loss', ['r' => 'today'])
            ->assertViewHas('report', fn($r) => $r['operating_income'] == 0.0 && $r['operating_expense'] == 0.0);
    }

    public function test_staff_cannot_open_reports(): void
    {
        [$staff, $farm] = $this->farmWith('staff');

        $this->report($staff, $farm, 'farms.reports.index')->assertForbidden();
        $this->report($staff, $farm, 'farms.reports.cash-flow')->assertForbidden();
        $this->report($staff, $farm, 'farms.reports.profit-loss')->assertForbidden();
    }

    public function test_manager_can_open_reports_and_expired_farm_can_still_view_them(): void
    {
        [$manager, $farm] = $this->farmWith('manager');
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->report($manager, $farm, 'farms.reports.cash-flow')->assertOk();
        $this->report($manager, $farm, 'farms.reports.profit-loss')->assertOk();
    }

    public function test_reports_hub_shows_the_summary(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->report($owner, $farm, 'farms.reports.index')
            ->assertOk()
            ->assertSee('নগদ প্রবাহ')
            ->assertSee('আনুমানিক লাভ-ক্ষতি');
    }
}
