<?php

namespace Tests\Feature;

use App\Livewire\CowList;
use App\Models\BreedingEvent;
use App\Models\Cow;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Plan;
use App\Models\User;
use App\Models\WeightRecord;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BreedingTest extends TestCase
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

    private function serve(User $user, Farm $farm, Cow $cow, array $override = [])
    {
        return $this->actingAs($user)->post(route('farms.breeding.store', $farm), array_merge([
            'cow_id' => $cow->id,
            'service_date' => now()->toDateString(),
            'method' => 'ai',
            'sire_name' => 'হলস্টেইন বীজ',
        ], $override));
    }

    private function makeEvent(Farm $farm, Cow $cow, array $override = []): BreedingEvent
    {
        $e = new BreedingEvent();
        $e->forceFill(array_merge([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'service_date' => now()->subDays(100)->toDateString(),
            'method' => 'ai',
            'sire_name' => 'বুল-১',
            'status' => 'served',
            'next_check_date' => now()->addDays(5)->toDateString(),
            'expected_calving_date' => now()->addDays(183)->toDateString(),
            'source' => 'manual',
        ], $override))->save();

        return $e;
    }

    public function test_staff_records_a_service_with_default_dates(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->serve($staff, $farm, $cow)->assertRedirect();

        $e = BreedingEvent::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals($farm->id, $e->farm_id);
        $this->assertEquals($staff->id, $e->created_by);
        $this->assertEquals('served', $e->status);
        $this->assertEquals(now()->addDays(60)->toDateString(), $e->next_check_date->toDateString());
        $this->assertEquals(now()->addDays(283)->toDateString(), $e->expected_calving_date->toDateString());
    }

    public function test_only_active_adult_females_can_be_served(): void
    {
        [$owner, $farm] = $this->farmWith();

        $bull = Cow::factory()->create(['farm_id' => $farm->id, 'sex' => 'male', 'category' => 'bull']);
        $calf = Cow::factory()->create(['farm_id' => $farm->id, 'category' => 'calf']);
        $sold = Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'sold']);

        foreach ([$bull, $calf, $sold] as $cow) {
            $this->serve($owner, $farm, $cow)->assertSessionHasErrors('cow_id');
        }

        $this->assertEquals(0, BreedingEvent::withoutGlobalScopes()->count());
    }

    public function test_cow_of_another_farm_is_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreign = Cow::factory()->create(['farm_id' => $other->id]);

        $this->serve($owner, $farm, $foreign)->assertSessionHasErrors('cow_id');
    }

    public function test_pregnant_cow_cannot_be_served_again(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $this->makeEvent($farm, $cow, ['status' => 'pregnant', 'next_check_date' => null]);

        $this->serve($owner, $farm, $cow)->assertSessionHasErrors('cow_id');

        $this->assertEquals(1, BreedingEvent::withoutGlobalScopes()->count());
    }

    public function test_new_service_closes_the_older_unresolved_one(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $older = $this->makeEvent($farm, $cow);

        $this->serve($owner, $farm, $cow)->assertSessionHasNoErrors();

        $older->refresh();
        $this->assertEquals('open', $older->status);
        $this->assertNull($older->next_check_date);
        $this->assertEquals(1, BreedingEvent::withoutGlobalScopes()->where('status', 'served')->count());
    }

    public function test_owner_cost_becomes_a_linked_expense_and_staff_cost_is_ignored(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->serve($owner, $farm, $cow, ['cost_amount' => 600, 'pay_mode' => 'paid', 'pay_method' => 'cash'])
            ->assertSessionHasNoErrors();

        $expense = Expense::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals('breeding', $expense->category);
        $this->assertEquals($cow->id, $expense->cow_id);
        $this->assertEquals($expense->id, BreedingEvent::withoutGlobalScopes()->first()->expense_id);

        $staff = User::factory()->create();
        $farm->users()->attach($staff->id, ['role' => 'staff']);
        $cow2 = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->serve($staff, $farm, $cow2, ['cost_amount' => 500, 'pay_mode' => 'paid', 'pay_method' => 'cash'])->assertRedirect();

        $this->assertEquals(1, Expense::withoutGlobalScopes()->count());
        $this->assertEquals(2, BreedingEvent::withoutGlobalScopes()->count());
    }

    public function test_check_result_can_confirm_pregnancy_or_mark_open(): void
    {
        [$owner, $farm] = $this->farmWith();
        $a = Cow::factory()->create(['farm_id' => $farm->id]);
        $b = Cow::factory()->create(['farm_id' => $farm->id]);
        $preg = $this->makeEvent($farm, $a);
        $notPreg = $this->makeEvent($farm, $b);
        $expected = now()->addDays(200)->toDateString();

        $this->actingAs($owner)->post(route('farms.breeding.check', [$farm, $preg]), [
            'result' => 'pregnant',
            'checked_on' => now()->toDateString(),
            'expected_calving_date' => $expected,
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('farms.breeding.check', [$farm, $notPreg]), [
            'result' => 'open',
            'checked_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $preg->refresh();
        $this->assertEquals('pregnant', $preg->status);
        $this->assertNull($preg->next_check_date);
        $this->assertEquals($expected, $preg->expected_calving_date->toDateString());
        $this->assertEquals('open', $notPreg->fresh()->status);
    }

    public function test_calving_creates_calves_updates_dam_and_records_birth_weight(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $dam = Cow::factory()->create(['farm_id' => $farm->id, 'category' => 'heifer', 'tag_number' => 'DAM1']);
        $event = $this->makeEvent($farm, $dam, [
            'status' => 'pregnant',
            'next_check_date' => null,
            'service_date' => now()->subDays(285)->toDateString(),
            'sire_name' => 'বুল-১',
        ]);

        $this->actingAs($staff)->post(route('farms.breeding.calve', [$farm, $event]), [
            'calved_on' => now()->toDateString(),
            'calves' => [
                ['tag_number' => 'C-101', 'sex' => 'female', 'name' => '', 'weight_kg' => 28.5, 'alive' => '1'],
                ['tag_number' => 'C-102', 'sex' => 'male', 'alive' => '1'],
            ],
            'update_dam' => '1',
        ])->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertEquals('calved', $event->status);
        $this->assertEquals(now()->toDateString(), $event->outcome_date->toDateString());

        $calves = Cow::withoutGlobalScopes()->where('mother_id', $dam->id)->orderBy('tag_number')->get();
        $this->assertCount(2, $calves);

        $first = $calves->first();
        $this->assertEquals('C-101', $first->tag_number);
        $this->assertEquals('calf', $first->category);
        $this->assertEquals('born', $first->acquisition_type);
        $this->assertEquals('বুল-১', $first->sire_name);
        $this->assertEquals('active', $first->status);
        $this->assertEquals($staff->id, $first->created_by);

        $weight = WeightRecord::withoutGlobalScopes()->where('cow_id', $first->id)->firstOrFail();
        $this->assertEquals(28.5, (float) $weight->weight_kg);

        $this->assertEquals('milking', $dam->fresh()->category);
        $this->assertCount(2, $event->calvesBorn());
    }

    public function test_stillborn_calf_is_recorded_without_using_a_slot(): void
    {
        [$owner, $farm] = $this->farmWith();
        Plan::where('slug', 'basic')->update(['max_cows' => 1]);
        $dam = Cow::factory()->create(['farm_id' => $farm->id]);
        $event = $this->makeEvent($farm, $dam, ['status' => 'pregnant', 'next_check_date' => null, 'service_date' => now()->subDays(285)->toDateString()]);

        $this->actingAs($owner)->post(route('farms.breeding.calve', [$farm, $event]), [
            'calved_on' => now()->toDateString(),
            'calves' => [['tag_number' => 'SB-1', 'sex' => 'female']], // alive নেই, মানে মৃত
        ])->assertSessionHasNoErrors();

        $calf = Cow::withoutGlobalScopes()->where('tag_number', 'SB-1')->firstOrFail();
        $this->assertEquals('dead', $calf->status);
        $this->assertEquals(now()->toDateString(), $calf->exit_date->toDateString());
        $this->assertEquals('calved', $event->fresh()->status);
    }

    public function test_calving_respects_the_plan_limit_and_rolls_back(): void
    {
        [$owner, $farm] = $this->farmWith();
        Plan::where('slug', 'basic')->update(['max_cows' => 2]); // মা গরু ১টি জায়গা নিয়েছে, বাকি ১টি
        $dam = Cow::factory()->create(['farm_id' => $farm->id]);
        $event = $this->makeEvent($farm, $dam, ['status' => 'pregnant', 'next_check_date' => null, 'service_date' => now()->subDays(285)->toDateString()]);

        $this->actingAs($owner)->post(route('farms.breeding.calve', [$farm, $event]), [
            'calved_on' => now()->toDateString(),
            'calves' => [
                ['tag_number' => 'T-1', 'sex' => 'female', 'alive' => '1'],
                ['tag_number' => 'T-2', 'sex' => 'male', 'alive' => '1'],
            ],
        ])->assertSessionHasErrors('calves');

        $this->assertEquals(1, Cow::withoutGlobalScopes()->count());
        $this->assertEquals('pregnant', $event->fresh()->status);
    }

    public function test_calf_tags_must_be_unique(): void
    {
        [$owner, $farm] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'DUP1']);
        $dam = Cow::factory()->create(['farm_id' => $farm->id]);
        $event = $this->makeEvent($farm, $dam, ['status' => 'pregnant', 'next_check_date' => null, 'service_date' => now()->subDays(285)->toDateString()]);

        $this->actingAs($owner)->post(route('farms.breeding.calve', [$farm, $event]), [
            'calved_on' => now()->toDateString(),
            'calves' => [['tag_number' => 'DUP1', 'sex' => 'female', 'alive' => '1']],
        ])->assertSessionHasErrors('calves.0.tag_number');

        $this->actingAs($owner)->post(route('farms.breeding.calve', [$farm, $event]), [
            'calved_on' => now()->toDateString(),
            'calves' => [
                ['tag_number' => 'SAME', 'sex' => 'female', 'alive' => '1'],
                ['tag_number' => 'SAME', 'sex' => 'male', 'alive' => '1'],
            ],
        ])->assertSessionHasErrors('calves.0.tag_number');
    }

    public function test_calving_date_rules(): void
    {
        [$owner, $farm] = $this->farmWith();
        $dam = Cow::factory()->create(['farm_id' => $farm->id]);
        $event = $this->makeEvent($farm, $dam, ['status' => 'pregnant', 'next_check_date' => null, 'service_date' => now()->subDays(100)->toDateString()]);
        $calf = [['tag_number' => 'D-1', 'sex' => 'female', 'alive' => '1']];

        $this->actingAs($owner)->post(route('farms.breeding.calve', [$farm, $event]), [
            'calved_on' => now()->addDay()->toDateString(),
            'calves' => $calf,
        ])->assertSessionHasErrors('calved_on');

        $this->actingAs($owner)->post(route('farms.breeding.calve', [$farm, $event]), [
            'calved_on' => now()->subDays(150)->toDateString(),
            'calves' => $calf,
        ])->assertSessionHasErrors('calved_on');
    }

    public function test_abortion_is_only_possible_from_pregnant(): void
    {
        [$owner, $farm] = $this->farmWith();
        $a = Cow::factory()->create(['farm_id' => $farm->id]);
        $b = Cow::factory()->create(['farm_id' => $farm->id]);
        $served = $this->makeEvent($farm, $a);
        $pregnant = $this->makeEvent($farm, $b, ['status' => 'pregnant', 'next_check_date' => null]);

        $this->actingAs($owner)->post(route('farms.breeding.abort', [$farm, $served]), ['outcome_date' => now()->toDateString()])
            ->assertSessionHasErrors('outcome_date');

        $this->actingAs($owner)->post(route('farms.breeding.abort', [$farm, $pregnant]), ['outcome_date' => now()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertEquals('served', $served->fresh()->status);
        $this->assertEquals('aborted', $pregnant->fresh()->status);
    }

    public function test_staff_cannot_edit_or_delete_but_manager_can_edit(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $event = $this->makeEvent($farm, $cow);

        $payload = ['service_date' => now()->subDays(100)->toDateString(), 'method' => 'ai', 'sire_name' => 'নতুন বীজ'];

        $this->actingAs($staff)->put(route('farms.breeding.update', [$farm, $event]), $payload)->assertForbidden();
        $this->actingAs($staff)->delete(route('farms.breeding.destroy', [$farm, $event]))->assertForbidden();

        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $this->actingAs($manager)->put(route('farms.breeding.update', [$farm, $event]), $payload)->assertRedirect();
        $this->assertEquals('নতুন বীজ', $event->fresh()->sire_name);

        $this->actingAs($farm->owner)->delete(route('farms.breeding.destroy', [$farm, $event]))->assertRedirect();
        $this->assertSoftDeleted('breeding_events', ['id' => $event->id]);
    }

    public function test_breeding_event_of_another_farm_is_not_reachable(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreignCow = Cow::factory()->create(['farm_id' => $other->id]);
        $foreign = $this->makeEvent($other, $foreignCow);

        $this->actingAs($owner)->get(route('farms.breeding.show', [$farm, $foreign]))->assertNotFound();
        $this->actingAs($owner)->post(route('farms.breeding.check', [$farm, $foreign]), [
            'result' => 'open',
            'checked_on' => now()->toDateString(),
        ])->assertNotFound();
    }

    public function test_tasks_include_due_checks_and_calvings_for_active_cows(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();

        $check = Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'CHK-A']);
        $calving = Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'PRG-B']);
        $late = Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'LATE-C']);
        $done = Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'DONE-D']);
        $sold = Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'SOLD-E', 'status' => 'sold']);
        $foreign = Cow::factory()->create(['farm_id' => $other->id, 'tag_number' => 'OTHR-F']);

        $this->makeEvent($farm, $check, ['next_check_date' => now()->addDays(5)->toDateString()]);
        $this->makeEvent($farm, $calving, ['status' => 'pregnant', 'next_check_date' => null, 'expected_calving_date' => now()->addDays(10)->toDateString()]);
        $this->makeEvent($farm, $late, ['next_check_date' => now()->addDays(60)->toDateString()]);
        $this->makeEvent($farm, $done, ['status' => 'calved']);
        $this->makeEvent($farm, $sold);
        $this->makeEvent($other, $foreign);

        $this->actingAs($owner)
            ->get(route('farms.health.tasks', $farm))
            ->assertOk()
            ->assertSee('CHK-A')
            ->assertSee('PRG-B')
            ->assertDontSee('LATE-C')
            ->assertDontSee('DONE-D')
            ->assertDontSee('SOLD-E')
            ->assertDontSee('OTHR-F');
    }

    public function test_expired_farm_cannot_record_a_service(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->serve($owner, $farm, $cow)->assertSessionHas('error');

        $this->assertEquals(0, BreedingEvent::withoutGlobalScopes()->count());
    }

    public function test_cow_list_can_be_filtered_to_pregnant_cows(): void
    {
        [$owner, $farm] = $this->farmWith();
        $pregnant = Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'PREG1']);
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'PLAIN1']);
        $this->makeEvent($farm, $pregnant, ['status' => 'pregnant', 'next_check_date' => null]);

        Livewire::actingAs($owner)
            ->test(CowList::class, ['farmId' => $farm->id])
            ->set('category', 'pregnant')
            ->assertSee('PREG1')
            ->assertDontSee('PLAIN1');
    }
}
