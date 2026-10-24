<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\FeedRecord;
use App\Models\FeedType;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
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

    private function type(Farm $farm, array $override = []): FeedType
    {
        $t = new FeedType(array_merge(['name' => 'ভুসি', 'unit' => 'kg', 'price_per_unit' => 20, 'is_active' => true], $override));
        $t->forceFill(['farm_id' => $farm->id])->save();

        return $t;
    }

    private function feed(User $user, Farm $farm, FeedType $type, array $override = [])
    {
        return $this->actingAs($user)->post(route('farms.feed.store', $farm), array_merge([
            'fed_on' => now()->toDateString(),
            'feed_type_id' => $type->id,
            'quantity' => 10,
            'target' => 'all',
        ], $override));
    }

    public function test_staff_records_feed_for_the_whole_herd_with_head_count_snapshot(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        Cow::factory()->count(3)->create(['farm_id' => $farm->id]);
        Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'sold']);
        $type = $this->type($farm);

        $this->feed($staff, $farm, $type)->assertSessionHasNoErrors();

        $r = FeedRecord::withoutGlobalScopes()->firstOrFail();
        $this->assertEquals($farm->id, $r->farm_id);
        $this->assertEquals($staff->id, $r->created_by);
        $this->assertEquals(3, $r->head_count);
        $this->assertEquals('kg', $r->unit);
        $this->assertEquals(3.33, $r->perHead());
    }

    public function test_category_target_counts_only_that_category(): void
    {
        [$owner, $farm] = $this->farmWith();
        Cow::factory()->count(2)->create(['farm_id' => $farm->id, 'category' => 'milking']);
        Cow::factory()->create(['farm_id' => $farm->id, 'category' => 'dry']);
        $type = $this->type($farm);

        $this->feed($owner, $farm, $type, ['target' => 'category', 'category' => 'milking'])->assertSessionHasNoErrors();
        $this->assertEquals(2, FeedRecord::withoutGlobalScopes()->first()->head_count);

        $this->feed($owner, $farm, $type, ['target' => 'category', 'category' => 'bull'])->assertSessionHasErrors('target');
        $this->assertEquals(1, FeedRecord::withoutGlobalScopes()->count());
    }

    public function test_cow_target_needs_an_active_cow_of_this_farm(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $sold = Cow::factory()->create(['farm_id' => $farm->id, 'status' => 'sold']);
        $foreign = Cow::factory()->create(['farm_id' => $other->id]);
        $type = $this->type($farm);

        $this->feed($owner, $farm, $type, ['target' => 'cow', 'cow_id' => $cow->id])->assertSessionHasNoErrors();
        $this->assertEquals(1, FeedRecord::withoutGlobalScopes()->first()->head_count);

        $this->feed($owner, $farm, $type, ['target' => 'cow', 'cow_id' => $sold->id])->assertSessionHasErrors('cow_id');
        $this->feed($owner, $farm, $type, ['target' => 'cow', 'cow_id' => $foreign->id])->assertSessionHasErrors('cow_id');
    }

    public function test_feed_type_of_another_farm_or_inactive_type_is_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $farm->id]);

        $foreign = $this->type($other);
        $inactive = $this->type($farm, ['name' => 'পুরোনো', 'is_active' => false]);

        $this->feed($owner, $farm, $foreign)->assertSessionHasErrors('feed_type_id');
        $this->feed($owner, $farm, $inactive)->assertSessionHasErrors('feed_type_id');
    }

    public function test_quantity_and_date_rules(): void
    {
        [$owner, $farm] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $farm->id]);
        $type = $this->type($farm);

        $this->feed($owner, $farm, $type, ['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->feed($owner, $farm, $type, ['quantity' => -3])->assertSessionHasErrors('quantity');
        $this->feed($owner, $farm, $type, ['fed_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('fed_on');

        $this->assertEquals(0, FeedRecord::withoutGlobalScopes()->count());
    }

    public function test_staff_cannot_manage_types_or_delete_records_but_manager_can(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        Cow::factory()->create(['farm_id' => $farm->id]);
        $type = $this->type($farm);
        $this->feed($staff, $farm, $type);
        $record = FeedRecord::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($staff)->get(route('farms.feed.types', $farm))->assertForbidden();
        $this->actingAs($staff)->post(route('farms.feed.types.store', $farm), ['name' => 'নতুন', 'unit' => 'kg'])->assertForbidden();
        $this->actingAs($staff)->delete(route('farms.feed.destroy', [$farm, $record]))->assertForbidden();

        $manager = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);

        $this->actingAs($manager)->post(route('farms.feed.types.store', $farm), ['name' => 'সাইলেজ', 'unit' => 'kg', 'price_per_unit' => 15])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('farms.feed.types.toggle', [$farm, $type]))->assertSessionHas('success');
        $this->assertFalse($type->fresh()->is_active);

        $this->actingAs($manager)->delete(route('farms.feed.destroy', [$farm, $record]))->assertSessionHas('success');
        $this->assertSoftDeleted('feed_records', ['id' => $record->id]);
    }

    public function test_default_types_are_added_once(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)->post(route('farms.feed.types.defaults', $farm))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('farms.feed.types.defaults', $farm))->assertSessionHas('success');

        $this->assertEquals(5, FeedType::withoutGlobalScopes()->where('farm_id', $farm->id)->count());
    }

    public function test_type_name_is_unique_per_farm(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$owner2, $farm2] = $this->farmWith();

        $this->actingAs($owner)->post(route('farms.feed.types.store', $farm), ['name' => 'ভুসি', 'unit' => 'kg'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('farms.feed.types.store', $farm), ['name' => 'ভুসি', 'unit' => 'kg'])->assertSessionHasErrors('name');
        $this->actingAs($owner2)->post(route('farms.feed.types.store', $farm2), ['name' => 'ভুসি', 'unit' => 'kg'])->assertSessionHasNoErrors();
    }

    public function test_estimated_cost_is_only_for_finance_roles(): void
    {
        [$owner, $farm] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $farm->id]);
        $type = $this->type($farm, ['price_per_unit' => 20]);
        $this->feed($owner, $farm, $type, ['quantity' => 10]);

        $staff = User::factory()->create();
        $farm->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($owner)->get(route('farms.feed.index', $farm))
            ->assertOk()
            ->assertViewHas('estimated', fn($v) => abs($v - 200.0) < 0.01);

        $this->actingAs($staff)->get(route('farms.feed.index', $farm))
            ->assertOk()
            ->assertViewHas('estimated', fn($v) => $v === null);
    }

    public function test_expired_farm_cannot_record_feed(): void
    {
        [$owner, $farm] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $farm->id]);
        $type = $this->type($farm);
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->feed($owner, $farm, $type)->assertSessionHas('error');

        $this->assertEquals(0, FeedRecord::withoutGlobalScopes()->count());
    }

    public function test_feed_record_of_another_farm_is_not_reachable(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$otherOwner, $other] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $other->id]);
        $type = $this->type($other);
        $this->feed($otherOwner, $other, $type);
        $foreign = FeedRecord::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($owner)->delete(route('farms.feed.destroy', [$farm, $foreign]))->assertNotFound();
    }
}
