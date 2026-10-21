<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\MilkRecord;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilkTest extends TestCase
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

    private function save(User $user, Farm $farm, array $rows, ?string $date = null)
    {
        return $this->actingAs($user)->post(route('farms.milk.save', $farm), [
            'date' => $date ?? now()->toDateString(),
            'rows' => $rows,
        ]);
    }

    public function test_staff_can_record_daily_milk_and_total_is_computed(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->save($staff, $farm, [$cow->id => ['1' => 5, '2' => 6.5, '3' => '']])
            ->assertRedirect();

        $record = MilkRecord::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals($farm->id, $record->farm_id);
        $this->assertEquals($staff->id, $record->created_by);
        $this->assertEquals(11.5, (float) $record->total_liters);
    }

    public function test_saving_the_same_day_again_updates_instead_of_duplicating(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->save($owner, $farm, [$cow->id => ['1' => 5, '2' => 6]]);
        $this->save($owner, $farm, [$cow->id => ['1' => 8, '2' => 0]]);

        $this->assertEquals(1, MilkRecord::withoutGlobalScopes()->count());
        $this->assertEquals(8.0, (float) MilkRecord::withoutGlobalScopes()->first()->total_liters);
    }

    public function test_blank_rows_are_ignored_but_zero_is_saved(): void
    {
        [$owner, $farm] = $this->farmWith();
        $a = Cow::factory()->create(['farm_id' => $farm->id]);
        $b = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->save($owner, $farm, [
            $a->id => ['1' => '', '2' => '', '3' => ''],
            $b->id => ['1' => '0', '2' => '', '3' => ''],
        ])->assertRedirect();

        $this->assertEquals(1, MilkRecord::withoutGlobalScopes()->count());
        $this->assertEquals($b->id, MilkRecord::withoutGlobalScopes()->first()->cow_id);
    }

    public function test_cow_of_another_farm_is_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreign = Cow::factory()->create(['farm_id' => $other->id]);

        $this->save($owner, $farm, [$foreign->id => ['1' => 5]])->assertStatus(422);

        $this->assertEquals(0, MilkRecord::withoutGlobalScopes()->count());
    }

    public function test_sold_cow_cannot_receive_milk(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'sold']);

        $this->save($owner, $farm, [$cow->id => ['1' => 5]])->assertStatus(422);
    }

    public function test_invalid_values_are_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->save($owner, $farm, [$cow->id => ['1' => -2]])
            ->assertSessionHasErrors('rows.' . $cow->id . '.1');

        $this->save($owner, $farm, [$cow->id => ['1' => 5]], now()->addDay()->toDateString())
            ->assertSessionHasErrors('date');

        $this->assertEquals(0, MilkRecord::withoutGlobalScopes()->count());
    }

    public function test_monthly_summary_adds_up(): void
    {
        [$owner, $farm] = $this->farmWith();
        $a = Cow::factory()->create(['farm_id' => $farm->id]);
        $b = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->save($owner, $farm, [$a->id => ['1' => 5, '2' => 6], $b->id => ['1' => 10]]);

        $this->actingAs($owner)
            ->get(route('farms.milk.index', $farm))
            ->assertOk()
            ->assertViewHas('totals', fn($t) => (float) $t->total === 21.0 && (int) $t->records === 2);
    }

    public function test_entry_page_lists_only_own_milking_cows(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();

        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'MINE1']);
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'DRY77', 'category' => 'dry']);
        Cow::factory()->create(['farm_id' => $other->id, 'tag_number' => 'THEIRS9']);

        $this->actingAs($owner)
            ->get(route('farms.milk.entry', $farm))
            ->assertOk()
            ->assertSee('MINE1')
            ->assertDontSee('DRY77')
            ->assertDontSee('THEIRS9');
    }

    public function test_expired_farm_cannot_save_milk(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->save($owner, $farm, [$cow->id => ['1' => 5]])->assertSessionHas('error');

        $this->assertEquals(0, MilkRecord::withoutGlobalScopes()->count());
    }

    public function test_sold_vs_produced_tile_is_only_for_finance_roles(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $this->save($owner, $farm, [$cow->id => ['1' => 5]]);

        $staff = User::factory()->create();
        $farm->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($owner)->get(route('farms.milk.index', $farm))->assertSee('বিক্রি হয়েছে (লিটার)');
        $this->actingAs($staff)->get(route('farms.milk.index', $farm))->assertOk()->assertDontSee('বিক্রি হয়েছে (লিটার)');
    }
}
