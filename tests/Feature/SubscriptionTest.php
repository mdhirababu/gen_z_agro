<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PlanSeeder::class);
    }

    private function makeFarm(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['owner_user_id' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        app(SubscriptionService::class)->startTrial($farm);

        return [$owner, $farm->fresh()];
    }

    private function pendingPayment(Farm $farm, string $trx = 'TRX1'): SubscriptionPayment
    {
        return $farm->subscriptionPayments()->create([
            'subscription_id' => $farm->subscription->id,
            'plan_id' => $farm->subscription->plan_id,
            'amount' => 500,
            'method' => 'bkash',
            'trx_id' => $trx,
        ]);
    }

    public function test_new_farm_starts_on_trial(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('farms.store'), ['name' => 'Trial Farm']);

        $farm = Farm::where('name', 'Trial Farm')->firstOrFail();
        $this->assertEquals('trial', $farm->subscription->currentStatus());

        $days = $farm->subscription->daysLeft();
        $this->assertGreaterThanOrEqual(13, $days);
        $this->assertLessThanOrEqual(14, $days);
    }

    public function test_expired_farm_is_read_only_but_viewable(): void
    {
        [$owner, $farm] = $this->makeFarm();
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->get(route('farms.dashboard', $farm))
            ->assertOk();

        $this->actingAs($owner)
            ->put(route('farms.update', $farm), ['name' => 'Changed'])
            ->assertSessionHas('error');

        $this->assertNotEquals('Changed', $farm->fresh()->name);
    }

    public function test_expired_farm_can_still_open_billing(): void
    {
        [$owner, $farm] = $this->makeFarm();
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->get(route('farms.billing.index', $farm))
            ->assertOk();
    }

    public function test_staff_cannot_open_billing(): void
    {
        [, $farm] = $this->makeFarm();
        $staff = User::factory()->create();
        $farm->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('farms.billing.index', $farm))
            ->assertForbidden();
    }

    public function test_payment_amount_comes_from_plan_not_request(): void
    {
        [$owner, $farm] = $this->makeFarm();
        $plan = Plan::where('slug', 'basic')->first();

        $this->actingAs($owner)->post(route('farms.billing.store', $farm), [
            'plan_id' => $plan->id,
            'method' => 'bkash',
            'trx_id' => 'ABC123',
            'amount' => 1, // এটা উপেক্ষা হবে
        ])->assertSessionHas('success');

        $payment = $farm->subscriptionPayments()->first();
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals($plan->price, $payment->amount);
    }

    public function test_duplicate_trx_id_is_rejected(): void
    {
        [$owner, $farm] = $this->makeFarm();
        $payment = $this->pendingPayment($farm, 'DUP1');
        $payment->forceFill(['status' => 'rejected'])->save();

        $this->actingAs($owner)->post(route('farms.billing.store', $farm), [
            'plan_id' => $farm->subscription->plan_id,
            'method' => 'bkash',
            'trx_id' => 'DUP1',
        ])->assertSessionHasErrors('trx_id');
    }

    public function test_normal_user_cannot_approve_payment(): void
    {
        [$owner, $farm] = $this->makeFarm();
        $payment = $this->pendingPayment($farm);

        $this->actingAs($owner)
            ->post(route('admin.payments.approve', $payment))
            ->assertNotFound();

        $this->assertEquals('pending', $payment->fresh()->status);
    }

    public function test_super_admin_approval_activates_subscription(): void
    {
        [, $farm] = $this->makeFarm();
        $payment = $this->pendingPayment($farm);
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $payment))
            ->assertSessionHas('success');

        $subscription = $farm->subscription->fresh();
        $this->assertEquals('active', $subscription->currentStatus());
        $this->assertTrue($subscription->ends_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertEquals('approved', $payment->fresh()->status);
    }

    public function test_payment_cannot_be_approved_twice(): void
    {
        [, $farm] = $this->makeFarm();
        $payment = $this->pendingPayment($farm);
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->post(route('admin.payments.approve', $payment));
        $endsAt = $farm->subscription->fresh()->ends_at;

        $this->actingAs($admin)
            ->post(route('admin.payments.approve', $payment))
            ->assertSessionHas('error');

        $this->assertTrue($endsAt->equalTo($farm->subscription->fresh()->ends_at));
    }

    public function test_member_limit_of_plan_is_enforced(): void
    {
        [$owner, $farm] = $this->makeFarm(); // Basic: সর্বোচ্চ ৩ জন (মালিকসহ)
        [$u2, $u3, $u4] = User::factory()->count(3)->create();

        foreach ([$u2, $u3] as $u) {
            $this->actingAs($owner)->post(route('farms.members.store', $farm), [
                'email' => $u->email,
                'role' => 'staff',
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($owner)->post(route('farms.members.store', $farm), [
            'email' => $u4->email,
            'role' => 'staff',
        ])->assertSessionHasErrors('email');

        $this->assertEquals(3, $farm->users()->count());
    }
}
