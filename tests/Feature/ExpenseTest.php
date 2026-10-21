<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Payment;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTest extends TestCase
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

    private function data(array $override = []): array
    {
        return array_merge([
            'category' => 'feed',
            'expense_date' => now()->toDateString(),
            'total_amount' => 5000,
            'description' => 'ভুসি ১০ বস্তা',
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ], $override);
    }

    private function create(User $user, Farm $farm, array $override = []): Expense
    {
        $this->actingAs($user)
            ->post(route('farms.expenses.store', $farm), $this->data($override))
            ->assertSessionHasNoErrors();

        return Expense::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private function makeDirect(Farm $farm, array $override = []): Expense
    {
        $e = new Expense(['category' => 'feed', 'expense_date' => now()->toDateString(), 'description' => 'নমুনা']);
        $e->forceFill(array_merge([
            'farm_id' => $farm->id,
            'total_amount' => 1000,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'active',
            'source' => 'manual',
        ], $override))->save();

        return $e;
    }

    public function test_fully_paid_expense_creates_payment_and_uses_server_farm(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)
            ->post(route('farms.expenses.store', $farm), $this->data(['farm_id' => 999]))
            ->assertRedirect();

        $e = Expense::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals($farm->id, $e->farm_id);
        $this->assertEquals($owner->id, $e->created_by);
        $this->assertEquals('paid', $e->payment_status);
        $this->assertEquals(5000.0, (float) $e->paid_amount);
        $this->assertEquals(1, Payment::withoutGlobalScopes()->count());
    }

    public function test_unpaid_expense_has_no_payment(): void
    {
        [$owner, $farm] = $this->farmWith();
        $e = $this->create($owner, $farm, ['pay_mode' => 'unpaid']);

        $this->assertEquals('unpaid', $e->payment_status);
        $this->assertEquals(5000.0, $e->dueAmount());
        $this->assertEquals(0, Payment::withoutGlobalScopes()->count());
    }

    public function test_partial_expense_records_partial_payment(): void
    {
        [$owner, $farm] = $this->farmWith();
        $e = $this->create($owner, $farm, ['pay_mode' => 'partial', 'paid_now' => 2000, 'pay_method' => 'bkash']);

        $this->assertEquals('partial', $e->payment_status);
        $this->assertEquals(2000.0, (float) $e->paid_amount);
        $this->assertEquals(3000.0, $e->dueAmount());
    }

    public function test_partial_amount_must_be_less_than_total(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)
            ->post(route('farms.expenses.store', $farm), $this->data(['pay_mode' => 'partial', 'paid_now' => 5000]))
            ->assertSessionHasErrors('paid_now');

        $this->assertEquals(0, Expense::withoutGlobalScopes()->count());
    }

    public function test_later_payment_completes_expense_and_overpayment_is_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        $e = $this->create($owner, $farm, ['pay_mode' => 'partial', 'paid_now' => 2000]);

        $this->actingAs($owner)
            ->post(route('farms.expenses.payments.store', [$farm, $e]), [
                'amount' => 4000,
                'paid_at' => now()->toDateString(),
                'method' => 'cash',
            ])->assertSessionHasErrors('amount');

        $this->actingAs($owner)
            ->post(route('farms.expenses.payments.store', [$farm, $e]), [
                'amount' => 3000,
                'paid_at' => now()->toDateString(),
                'method' => 'cash',
            ])->assertSessionHasNoErrors();

        $e->refresh();
        $this->assertEquals('paid', $e->payment_status);
        $this->assertEquals(5000.0, (float) $e->paid_amount);
    }

    public function test_voiding_a_payment_recalculates_status(): void
    {
        [$owner, $farm] = $this->farmWith();
        $e = $this->create($owner, $farm, ['pay_mode' => 'partial', 'paid_now' => 2000]);
        $payment = Payment::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('farms.payments.void', [$farm, $payment]), ['reason' => 'ভুল এন্ট্রি'])
            ->assertSessionHas('success');

        $e->refresh();
        $this->assertEquals('unpaid', $e->payment_status);
        $this->assertEquals(0.0, (float) $e->paid_amount);
        $this->assertEquals('void', $payment->fresh()->status);
    }

    public function test_voiding_expense_voids_payments_and_blocks_new_ones(): void
    {
        [$owner, $farm] = $this->farmWith();
        $e = $this->create($owner, $farm); // পুরো টাকা দেওয়া

        $this->actingAs($owner)
            ->post(route('farms.expenses.void', [$farm, $e]), ['reason' => 'ভুল খরচ'])
            ->assertSessionHas('success');

        $e->refresh();
        $this->assertEquals('void', $e->status);
        $this->assertEquals(0.0, $e->dueAmount());
        $this->assertEquals(0, Payment::withoutGlobalScopes()->where('status', 'active')->count());

        $this->actingAs($owner)
            ->post(route('farms.expenses.payments.store', [$farm, $e]), [
                'amount' => 100,
                'paid_at' => now()->toDateString(),
                'method' => 'cash',
            ])->assertSessionHasErrors('amount');
    }

    public function test_void_requires_a_reason(): void
    {
        [$owner, $farm] = $this->farmWith();
        $e = $this->create($owner, $farm);

        $this->actingAs($owner)
            ->post(route('farms.expenses.void', [$farm, $e]), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertEquals('active', $e->fresh()->status);
    }

    public function test_staff_cannot_use_expenses(): void
    {
        [$staff, $farm] = $this->farmWith('staff');

        $this->actingAs($staff)->get(route('farms.expenses.index', $farm))->assertForbidden();
        $this->actingAs($staff)->post(route('farms.expenses.store', $farm), $this->data())->assertForbidden();
    }

    public function test_manager_can_add_expense(): void
    {
        [$manager, $farm] = $this->farmWith('manager');

        $this->actingAs($manager)
            ->post(route('farms.expenses.store', $farm), $this->data())
            ->assertRedirect();

        $this->assertEquals(1, Expense::withoutGlobalScopes()->count());
    }

    public function test_expense_of_another_farm_is_not_reachable(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreign = $this->makeDirect($other);

        $this->actingAs($owner)->get(route('farms.expenses.show', [$farm, $foreign]))->assertNotFound();
        $this->actingAs($owner)->post(route('farms.expenses.void', [$farm, $foreign]), ['reason' => 'test'])->assertNotFound();
    }

    public function test_cow_from_another_farm_is_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreignCow = Cow::factory()->create(['farm_id' => $other->id]);

        $this->actingAs($owner)
            ->post(route('farms.expenses.store', $farm), $this->data(['cow_id' => $foreignCow->id]))
            ->assertSessionHasErrors('cow_id');
    }

    public function test_amount_is_locked_after_payment_but_editable_before(): void
    {
        [$owner, $farm] = $this->farmWith();

        $paid = $this->create($owner, $farm);
        $this->actingAs($owner)
            ->put(route('farms.expenses.update', [$farm, $paid]), $this->data(['total_amount' => 9999, 'description' => 'বদলানো']))
            ->assertRedirect();
        $paid->refresh();
        $this->assertEquals(5000.0, (float) $paid->total_amount);
        $this->assertEquals('বদলানো', $paid->description);

        $unpaid = $this->create($owner, $farm, ['pay_mode' => 'unpaid']);
        $this->actingAs($owner)
            ->put(route('farms.expenses.update', [$farm, $unpaid]), $this->data(['total_amount' => 7000]))
            ->assertRedirect();
        $this->assertEquals(7000.0, (float) $unpaid->fresh()->total_amount);
    }

    public function test_expired_farm_cannot_add_expenses(): void
    {
        [$owner, $farm] = $this->farmWith();
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->post(route('farms.expenses.store', $farm), $this->data())
            ->assertSessionHas('error');

        $this->assertEquals(0, Expense::withoutGlobalScopes()->count());
    }

    public function test_month_filter_limits_the_list(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->makeDirect($farm, ['description' => 'NEWFEED']);
        $this->makeDirect($farm, ['description' => 'OLDFEED', 'expense_date' => now()->subMonths(3)->toDateString()]);

        $this->actingAs($owner)->get(route('farms.expenses.index', $farm))
            ->assertSee('NEWFEED')->assertDontSee('OLDFEED');

        $this->actingAs($owner)->get(route('farms.expenses.index', [$farm, 'm' => 'all']))
            ->assertSee('NEWFEED')->assertSee('OLDFEED');
    }
}
