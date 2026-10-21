<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTest extends TestCase
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

    private function customerIn(Farm $farm, string $name = 'রহিম'): Customer
    {
        $c = new Customer(['name' => $name]);
        $c->forceFill(['farm_id' => $farm->id])->save();

        return $c;
    }

    private function saleData(array $override = []): array
    {
        return array_merge([
            'sale_type' => 'milk',
            'sold_at' => now()->toDateString(),
            'quantity' => 20,
            'unit_price' => 60,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ], $override);
    }

    private function sell(User $user, Farm $farm, array $override = []): Sale
    {
        $this->actingAs($user)
            ->post(route('farms.sales.store', $farm), $this->saleData($override))
            ->assertSessionHasNoErrors();

        return Sale::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_milk_total_is_computed_by_server(): void
    {
        [$owner, $farm] = $this->farmWith();

        $sale = $this->sell($owner, $farm, ['total_amount' => 1]); // এই মোট ধরা হবে না

        $this->assertEquals(1200.0, (float) $sale->total_amount);
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('লিটার', $sale->unit);
        $this->assertEquals($farm->id, $sale->farm_id);
        $this->assertEquals(1, Payment::withoutGlobalScopes()->count());
    }

    public function test_credit_sale_needs_a_customer(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)
            ->post(route('farms.sales.store', $farm), $this->saleData(['pay_mode' => 'unpaid']))
            ->assertSessionHasErrors('customer_id');

        $customer = $this->customerIn($farm);
        $sale = $this->sell($owner, $farm, ['pay_mode' => 'unpaid', 'customer_id' => $customer->id]);

        $this->assertEquals('unpaid', $sale->payment_status);
        $this->assertEquals(1200.0, $sale->dueAmount());
    }

    public function test_other_income_needs_description(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)
            ->post(route('farms.sales.store', $farm), [
                'sale_type' => 'other_income',
                'sold_at' => now()->toDateString(),
                'total_amount' => 3000,
                'pay_mode' => 'paid',
                'pay_method' => 'cash',
            ])->assertSessionHasErrors('description');
    }

    public function test_cow_sale_marks_cow_sold_and_frees_the_slot(): void
    {
        [$owner, $farm] = $this->farmWith();
        Plan::where('slug', 'basic')->update(['max_cows' => 1]);
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $customer = $this->customerIn($farm);

        $this->assertFalse($farm->fresh()->canAddCows());

        $sale = $this->sell($owner, $farm, [
            'sale_type' => 'cow',
            'cow_id' => $cow->id,
            'total_amount' => 100000,
            'pay_mode' => 'partial',
            'paid_now' => 40000,
            'customer_id' => $customer->id,
            'quantity' => null,
            'unit_price' => null,
        ]);

        $cow->refresh();
        $this->assertEquals('sold', $cow->status);
        $this->assertEquals(now()->toDateString(), $cow->exit_date->toDateString());
        $this->assertEquals('partial', $sale->payment_status);
        $this->assertEquals(60000.0, $sale->dueAmount());
        $this->assertTrue($farm->fresh()->canAddCows());
    }

    public function test_cow_cannot_be_sold_twice(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $data = ['sale_type' => 'cow', 'cow_id' => $cow->id, 'total_amount' => 90000, 'quantity' => null, 'unit_price' => null];

        $this->sell($owner, $farm, $data);

        $this->actingAs($owner)
            ->post(route('farms.sales.store', $farm), $this->saleData($data))
            ->assertSessionHasErrors('cow_id');

        $this->assertEquals(1, Sale::withoutGlobalScopes()->count());
    }

    public function test_voiding_a_cow_sale_restores_the_cow_and_voids_payments(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $sale = $this->sell($owner, $farm, [
            'sale_type' => 'cow',
            'cow_id' => $cow->id,
            'total_amount' => 90000,
            'quantity' => null,
            'unit_price' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('farms.sales.void', [$farm, $sale]), ['reason' => 'ক্রেতা নেননি'])
            ->assertSessionHas('success');

        $cow->refresh();
        $this->assertEquals('active', $cow->status);
        $this->assertNull($cow->exit_date);
        $this->assertEquals('void', $sale->fresh()->status);
        $this->assertEquals(0, Payment::withoutGlobalScopes()->where('status', 'active')->count());
    }

    public function test_voiding_cow_sale_is_blocked_when_cow_limit_is_full(): void
    {
        [$owner, $farm] = $this->farmWith();
        Plan::where('slug', 'basic')->update(['max_cows' => 1]);

        $cowA = Cow::factory()->create(['farm_id' => $farm->id]);
        $sale = $this->sell($owner, $farm, [
            'sale_type' => 'cow',
            'cow_id' => $cowA->id,
            'total_amount' => 90000,
            'quantity' => null,
            'unit_price' => null,
        ]);
        Cow::factory()->create(['farm_id' => $farm->id]); // জায়গা আবার ভরে গেছে

        $this->actingAs($owner)
            ->post(route('farms.sales.void', [$farm, $sale]), ['reason' => 'ভুল বিক্রি'])
            ->assertSessionHas('error');

        $this->assertEquals('sold', $cowA->fresh()->status);
        $this->assertEquals('active', $sale->fresh()->status);
    }

    public function test_customer_and_cow_of_another_farm_are_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreignCustomer = $this->customerIn($other);
        $foreignCow = Cow::factory()->create(['farm_id' => $other->id]);

        $this->actingAs($owner)
            ->post(route('farms.sales.store', $farm), $this->saleData(['customer_id' => $foreignCustomer->id]))
            ->assertSessionHasErrors('customer_id');

        $this->actingAs($owner)
            ->post(route('farms.sales.store', $farm), $this->saleData([
                'sale_type' => 'cow',
                'cow_id' => $foreignCow->id,
                'total_amount' => 50000,
            ]))->assertSessionHasErrors('cow_id');
    }

    public function test_staff_cannot_use_sales_or_customers(): void
    {
        [$staff, $farm] = $this->farmWith('staff');

        $this->actingAs($staff)->get(route('farms.sales.index', $farm))->assertForbidden();
        $this->actingAs($staff)->post(route('farms.sales.store', $farm), $this->saleData())->assertForbidden();
        $this->actingAs($staff)->get(route('farms.customers.index', $farm))->assertForbidden();
    }

    public function test_sale_of_another_farm_is_not_reachable(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$otherOwner, $other] = $this->farmWith();
        $foreign = $this->sell($otherOwner, $other);

        $this->actingAs($owner)->get(route('farms.sales.show', [$farm, $foreign]))->assertNotFound();
        $this->actingAs($owner)->post(route('farms.sales.void', [$farm, $foreign]), ['reason' => 'test'])->assertNotFound();
    }

    public function test_sale_update_cannot_change_amount(): void
    {
        [$owner, $farm] = $this->farmWith();
        $customer = $this->customerIn($farm);
        $sale = $this->sell($owner, $farm);

        $this->actingAs($owner)
            ->put(route('farms.sales.update', [$farm, $sale]), [
                'sold_at' => now()->toDateString(),
                'customer_id' => $customer->id,
                'description' => 'বদলানো',
                'total_amount' => 9999,
            ])->assertRedirect();

        $sale->refresh();
        $this->assertEquals(1200.0, (float) $sale->total_amount);
        $this->assertEquals('বদলানো', $sale->description);
        $this->assertEquals($customer->id, $sale->customer_id);
    }

    public function test_receiving_from_customer_pays_oldest_sale_first(): void
    {
        [$owner, $farm] = $this->farmWith();
        $customer = $this->customerIn($farm);

        $this->sell($owner, $farm, ['pay_mode' => 'unpaid', 'customer_id' => $customer->id, 'quantity' => 10, 'unit_price' => 100, 'sold_at' => now()->subDays(3)->toDateString()]);
        $this->sell($owner, $farm, ['pay_mode' => 'unpaid', 'customer_id' => $customer->id, 'quantity' => 20, 'unit_price' => 100]);

        $this->actingAs($owner)
            ->post(route('farms.customers.receive', [$farm, $customer]), [
                'amount' => 4000,
                'paid_at' => now()->toDateString(),
                'method' => 'cash',
            ])->assertSessionHasErrors('amount'); // বকেয়া মাত্র ৩০০০

        $this->actingAs($owner)
            ->post(route('farms.customers.receive', [$farm, $customer]), [
                'amount' => 1500,
                'paid_at' => now()->toDateString(),
                'method' => 'bkash',
            ])->assertSessionHasNoErrors();

        [$older, $newer] = Sale::withoutGlobalScopes()->orderBy('sold_at')->get()->all();

        $this->assertEquals('paid', $older->payment_status);
        $this->assertEquals('partial', $newer->payment_status);
        $this->assertEquals(500.0, (float) $newer->paid_amount);
    }

    public function test_customer_name_is_unique_and_customer_with_sales_cannot_be_removed(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)->post(route('farms.customers.store', $farm), ['name' => 'করিম'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('farms.customers.store', $farm), ['name' => 'করিম'])->assertSessionHasErrors('name');

        $customer = Customer::withoutGlobalScopes()->where('name', 'করিম')->firstOrFail();
        $this->sell($owner, $farm, ['pay_mode' => 'unpaid', 'customer_id' => $customer->id]);

        $this->actingAs($owner)
            ->delete(route('farms.customers.destroy', [$farm, $customer]))
            ->assertSessionHas('error');

        $this->assertNull($customer->fresh()->deleted_at);
    }

    public function test_expired_farm_cannot_sell(): void
    {
        [$owner, $farm] = $this->farmWith();
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->post(route('farms.sales.store', $farm), $this->saleData())
            ->assertSessionHas('error');

        $this->assertEquals(0, Sale::withoutGlobalScopes()->count());
    }

    public function test_cow_finance_tab_is_visible_only_to_owner_and_manager(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id, 'purchase_price' => 80000]);

        $expense = new Expense(['category' => 'feed', 'expense_date' => now()->toDateString(), 'cow_id' => $cow->id]);
        $expense->forceFill([
            'farm_id' => $farm->id,
            'total_amount' => 5000,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'active',
            'source' => 'manual',
        ])->save();

        $this->actingAs($owner)
            ->get(route('farms.cows.show', [$farm, $cow]))
            ->assertOk()
            ->assertSee('মোট বিনিয়োগ')
            ->assertSee('85,000');

        $staff = User::factory()->create();
        $farm->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('farms.cows.show', [$farm, $cow]))
            ->assertOk()
            ->assertDontSee('মোট বিনিয়োগ');
    }
}
