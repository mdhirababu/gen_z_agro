<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
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

    private function record(User $user, Farm $farm, array $override = [])
    {
        return $this->actingAs($user)->post(route('farms.health.store', $farm), array_merge([
            'kind' => 'treatment',
            'event_date' => now()->toDateString(),
            'title' => 'জ্বর',
        ], $override));
    }

    private function makeEvent(Farm $farm, Cow $cow, array $override = []): HealthEvent
    {
        $e = new HealthEvent();
        $e->forceFill(array_merge([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'kind' => 'vaccination',
            'event_date' => now()->subDays(100)->toDateString(),
            'vaccine_code' => 'fmd',
            'title' => 'ক্ষুরা রোগ (FMD)',
            'next_due_date' => now()->addDays(10)->toDateString(),
            'follow_up_status' => 'open',
            'source' => 'manual',
        ], $override))->save();

        return $e;
    }

    public function test_staff_can_record_a_treatment(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->record($staff, $farm, [
            'cow_ids' => [$cow->id],
            'milk_withdrawal_until' => now()->addDays(5)->toDateString(),
        ])->assertRedirect();

        $e = HealthEvent::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals($farm->id, $e->farm_id);
        $this->assertEquals($staff->id, $e->created_by);
        $this->assertEquals('ongoing', $e->outcome);
        $this->assertTrue($e->withdrawalActive());
    }

    public function test_treatment_needs_a_title(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->record($owner, $farm, ['cow_ids' => [$cow->id], 'title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_vaccination_takes_its_title_from_the_vaccine_and_opens_a_follow_up(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->record($owner, $farm, [
            'kind' => 'vaccination',
            'vaccine_code' => 'fmd',
            'title' => '',
            'cow_ids' => [$cow->id],
            'next_due_date' => now()->addDays(180)->toDateString(),
        ])->assertSessionHasNoErrors();

        $e = HealthEvent::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals('ক্ষুরা রোগ (FMD)', $e->title);
        $this->assertEquals('open', $e->follow_up_status);
    }

    public function test_new_vaccination_closes_only_the_same_cows_same_vaccine_follow_up(): void
    {
        [$owner, $farm] = $this->farmWith();
        $a = Cow::factory()->create(['farm_id' => $farm->id]);
        $b = Cow::factory()->create(['farm_id' => $farm->id]);

        $oldAFmd = $this->makeEvent($farm, $a);
        $oldBFmd = $this->makeEvent($farm, $b);
        $oldAAnthrax = $this->makeEvent($farm, $a, ['vaccine_code' => 'anthrax', 'title' => 'তড়কা (অ্যানথ্রাক্স)']);

        $this->record($owner, $farm, [
            'kind' => 'vaccination',
            'vaccine_code' => 'fmd',
            'cow_ids' => [$a->id],
            'next_due_date' => now()->addDays(180)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertEquals('done', $oldAFmd->fresh()->follow_up_status);
        $this->assertEquals('open', $oldBFmd->fresh()->follow_up_status);
        $this->assertEquals('open', $oldAAnthrax->fresh()->follow_up_status);
    }

    public function test_bulk_vaccination_creates_one_event_per_cow_and_one_shared_expense(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cows = Cow::factory()->count(3)->create(['farm_id' => $farm->id]);

        $this->record($owner, $farm, [
            'kind' => 'vaccination',
            'vaccine_code' => 'fmd',
            'cow_ids' => $cows->pluck('id')->all(),
            'cost_amount' => 3000,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(3, HealthEvent::withoutGlobalScopes()->count());
        $this->assertEquals(1, Expense::withoutGlobalScopes()->count());

        $expense = Expense::withoutGlobalScopes()->first();
        $this->assertNull($expense->cow_id);
        $this->assertEquals('medicine', $expense->category);
        $this->assertEquals('paid', $expense->payment_status);
        $this->assertEquals(1, HealthEvent::withoutGlobalScopes()->pluck('expense_id')->unique()->count());
    }

    public function test_single_cow_cost_is_linked_to_that_cow(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->record($owner, $farm, [
            'cow_ids' => [$cow->id],
            'cost_amount' => 800,
            'pay_mode' => 'partial',
            'paid_now' => 300,
            'pay_method' => 'bkash',
        ])->assertSessionHasNoErrors();

        $expense = Expense::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals($cow->id, $expense->cow_id);
        $this->assertEquals('partial', $expense->payment_status);
        $this->assertEquals(500.0, $expense->dueAmount());
    }

    public function test_staff_cost_is_ignored(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->record($staff, $farm, [
            'cow_ids' => [$cow->id],
            'cost_amount' => 500,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ])->assertRedirect();

        $this->assertEquals(1, HealthEvent::withoutGlobalScopes()->count());
        $this->assertEquals(0, Expense::withoutGlobalScopes()->count());
    }

    public function test_cow_of_another_farm_is_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreign = Cow::factory()->create(['farm_id' => $other->id]);

        $this->record($owner, $farm, ['cow_ids' => [$foreign->id]])
            ->assertSessionHasErrors('cow_ids.0');

        $this->assertEquals(0, HealthEvent::withoutGlobalScopes()->count());
    }

    public function test_date_rules(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->record($owner, $farm, ['cow_ids' => [$cow->id], 'next_due_date' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors('next_due_date');

        $this->record($owner, $farm, ['cow_ids' => [$cow->id], 'milk_withdrawal_until' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors('milk_withdrawal_until');

        $this->record($owner, $farm, ['cow_ids' => [$cow->id], 'event_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('event_date');
    }

    public function test_staff_cannot_edit_or_delete_but_manager_and_owner_can(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $event = $this->makeEvent($farm, $cow, ['kind' => 'treatment', 'vaccine_code' => null, 'title' => 'জ্বর', 'follow_up_status' => null, 'next_due_date' => null]);

        $payload = ['kind' => 'treatment', 'event_date' => now()->toDateString(), 'title' => 'নতুন নাম'];

        $this->actingAs($staff)->put(route('farms.health.update', [$farm, $event]), $payload)->assertForbidden();
        $this->actingAs($staff)->delete(route('farms.health.destroy', [$farm, $event]))->assertForbidden();

        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $this->actingAs($manager)->put(route('farms.health.update', [$farm, $event]), $payload)->assertRedirect();
        $this->assertEquals('নতুন নাম', $event->fresh()->title);

        $this->actingAs($farm->owner)->delete(route('farms.health.destroy', [$farm, $event]))->assertRedirect();
        $this->assertSoftDeleted('health_events', ['id' => $event->id]);
    }

    public function test_staff_can_mark_treatment_recovered(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $event = $this->makeEvent($farm, $cow, ['kind' => 'treatment', 'vaccine_code' => null, 'title' => 'জ্বর', 'outcome' => 'ongoing', 'follow_up_status' => null, 'next_due_date' => null]);

        $this->actingAs($staff)->post(route('farms.health.recover', [$farm, $event]))->assertSessionHas('success');

        $this->assertEquals('recovered', $event->fresh()->outcome);
    }

    public function test_marking_a_task_done_removes_it_from_the_task_list(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $event = $this->makeEvent($farm, $cow, ['title' => 'DONE-ME']);

        $this->actingAs($owner)->get(route('farms.health.tasks', $farm))->assertSee('DONE-ME');

        $this->actingAs($owner)->post(route('farms.health.done', [$farm, $event]))->assertSessionHas('success');

        $this->actingAs($owner)->get(route('farms.health.tasks', $farm))->assertDontSee('DONE-ME');
    }

    public function test_task_list_shows_due_and_overdue_for_active_cows_of_this_farm_only(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();

        $active = Cow::factory()->create(['farm_id' => $farm->id]);
        $sold = Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'sold']);
        $foreign = Cow::factory()->create(['farm_id' => $other->id]);

        $this->makeEvent($farm, $active, ['title' => 'OVERDUE-VAC', 'next_due_date' => now()->subDays(3)->toDateString()]);
        $this->makeEvent($farm, $active, ['title' => 'SOON-VAC', 'next_due_date' => now()->addDays(10)->toDateString()]);
        $this->makeEvent($farm, $active, ['title' => 'LATER-VAC', 'next_due_date' => now()->addDays(60)->toDateString()]);
        $this->makeEvent($farm, $sold, ['title' => 'SOLD-VAC']);
        $this->makeEvent($other, $foreign, ['title' => 'OTHER-VAC']);

        $this->actingAs($owner)
            ->get(route('farms.health.tasks', $farm))
            ->assertOk()
            ->assertSee('OVERDUE-VAC')
            ->assertSee('SOON-VAC')
            ->assertDontSee('LATER-VAC')
            ->assertDontSee('SOLD-VAC')
            ->assertDontSee('OTHER-VAC');
    }

    public function test_expired_farm_cannot_record_health(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->record($owner, $farm, ['cow_ids' => [$cow->id]])->assertSessionHas('error');

        $this->assertEquals(0, HealthEvent::withoutGlobalScopes()->count());
    }

    public function test_health_event_of_another_farm_is_not_reachable(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreignCow = Cow::factory()->create(['farm_id' => $other->id]);
        $foreign = $this->makeEvent($other, $foreignCow);

        $this->actingAs($owner)->get(route('farms.health.edit', [$farm, $foreign]))->assertNotFound();
        $this->actingAs($owner)->post(route('farms.health.done', [$farm, $foreign]))->assertNotFound();
    }

    public function test_milk_entry_warns_about_milk_withdrawal(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->makeEvent($farm, $cow, [
            'kind' => 'treatment',
            'vaccine_code' => null,
            'title' => 'ওষুধ',
            'event_date' => now()->toDateString(),
            'milk_withdrawal_until' => now()->addDays(3)->toDateString(),
            'outcome' => 'ongoing',
            'follow_up_status' => null,
            'next_due_date' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('farms.milk.entry', $farm))
            ->assertOk()
            ->assertSee('দুধ বিক্রি বন্ধ');
    }

    public function test_dashboard_shows_upcoming_tasks(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $this->makeEvent($farm, $cow, ['title' => 'DASH-VAC', 'next_due_date' => now()->addDays(5)->toDateString()]);

        $this->actingAs($owner)
            ->get(route('farms.dashboard', $farm))
            ->assertOk()
            ->assertViewHas('taskCount', 1)
            ->assertSee('DASH-VAC');
    }
}
