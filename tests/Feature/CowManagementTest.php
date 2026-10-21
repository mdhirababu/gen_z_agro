<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\Plan;
use App\Models\User;
use App\Models\WeightRecord;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CowManagementTest extends TestCase
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

    private function cowData(array $override = []): array
    {
        return array_merge([
            'tag_number' => 'CW101',
            'category' => 'milking',
            'sex' => 'female',
            'breed' => 'deshi',
            'birth_date' => now()->subYears(3)->toDateString(),
            'acquisition_type' => 'purchased',
            'purchase_date' => now()->subMonth()->toDateString(),
            'purchase_price' => 90000,
        ], $override);
    }

    public function test_cow_is_saved_in_current_farm_even_if_request_names_another_farm(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();

        $this->actingAs($owner)
            ->post(route('farms.cows.store', $farm), $this->cowData(['farm_id' => $other->id]))
            ->assertRedirect();

        $cow = Cow::withoutGlobalScopes()->where('tag_number', 'CW101')->firstOrFail();
        $this->assertEquals($farm->id, $cow->farm_id);
        $this->assertEquals($owner->id, $cow->created_by);
        $this->assertEquals('manual', $cow->source);
    }

    public function test_duplicate_tag_is_rejected_in_same_farm_but_allowed_in_another(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$owner2, $farm2] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'CW101']);

        $this->actingAs($owner)
            ->post(route('farms.cows.store', $farm), $this->cowData())
            ->assertSessionHasErrors('tag_number');

        $this->actingAs($owner2)
            ->post(route('farms.cows.store', $farm2), $this->cowData())
            ->assertSessionHasNoErrors();
    }

    public function test_mother_from_another_farm_is_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreignMother = Cow::factory()->create(['farm_id' => $other->id, 'sex' => 'female']);

        $this->actingAs($owner)
            ->post(route('farms.cows.store', $farm), $this->cowData(['mother_id' => $foreignMother->id]))
            ->assertSessionHasErrors('mother_id');
    }

    public function test_mother_must_be_female(): void
    {
        [$owner, $farm] = $this->farmWith();
        $bull = Cow::factory()->create(['farm_id' => $farm->id, 'sex' => 'male', 'category' => 'bull']);

        $this->actingAs($owner)
            ->post(route('farms.cows.store', $farm), $this->cowData(['mother_id' => $bull->id]))
            ->assertSessionHasErrors('mother_id');
    }

    public function test_cow_of_another_farm_cannot_be_opened_through_own_farm_url(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();
        $foreign = Cow::factory()->create(['farm_id' => $other->id]);

        $this->actingAs($owner)
            ->get(route('farms.cows.show', [$farm, $foreign]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('farms.cows.edit', [$farm, $foreign]))
            ->assertNotFound();
    }

    public function test_staff_cannot_edit_cow(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($staff)
            ->put(route('farms.cows.update', [$farm, $cow]), $this->cowData(['tag_number' => $cow->tag_number, 'name' => 'Hacked']))
            ->assertForbidden();

        $this->assertNotEquals('Hacked', $cow->fresh()->name);
    }

    public function test_manager_can_edit_cow(): void
    {
        [$manager, $farm] = $this->farmWith('manager');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($manager)
            ->put(route('farms.cows.update', [$farm, $cow]), $this->cowData(['tag_number' => $cow->tag_number, 'name' => 'Updated']))
            ->assertRedirect();

        $this->assertEquals('Updated', $cow->fresh()->name);
    }

    public function test_only_owner_can_delete_cow(): void
    {
        [$manager, $farm] = $this->farmWith('manager');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($manager)
            ->delete(route('farms.cows.destroy', [$farm, $cow]))
            ->assertForbidden();

        $owner = $farm->owner;
        $this->actingAs($owner)
            ->delete(route('farms.cows.destroy', [$farm, $cow]))
            ->assertRedirect();

        $this->assertSoftDeleted('cows', ['id' => $cow->id]);
    }

    public function test_plan_cow_limit_is_enforced(): void
    {
        [$owner, $farm] = $this->farmWith();
        Plan::where('slug', 'basic')->update(['max_cows' => 1]);

        $this->actingAs($owner)
            ->post(route('farms.cows.store', $farm), $this->cowData(['tag_number' => 'A1']))
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->post(route('farms.cows.store', $farm), $this->cowData(['tag_number' => 'A2']))
            ->assertSessionHasErrors('limit');

        $this->assertEquals(1, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->count());
    }

    public function test_expired_farm_cannot_register_cows(): void
    {
        [$owner, $farm] = $this->farmWith();
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->post(route('farms.cows.store', $farm), $this->cowData())
            ->assertSessionHas('error');

        $this->assertEquals(0, Cow::withoutGlobalScopes()->count());
    }

    public function test_born_on_farm_ignores_submitted_price(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)->post(route('farms.cows.store', $farm), $this->cowData([
            'tag_number' => 'CALF1',
            'category' => 'calf',
            'acquisition_type' => 'born',
            'birth_date' => now()->subDays(10)->toDateString(),
            'purchase_price' => 5000,
        ]))->assertSessionHasNoErrors();

        $cow = Cow::withoutGlobalScopes()->where('tag_number', 'CALF1')->firstOrFail();
        $this->assertEquals(0.0, (float) $cow->purchase_price);
        $this->assertEquals(0.0, $cow->baseValue());
    }

    public function test_category_and_sex_must_match(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)
            ->post(route('farms.cows.store', $farm), $this->cowData(['category' => 'milking', 'sex' => 'male']))
            ->assertSessionHasErrors('sex');
    }

    public function test_staff_can_add_weight(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($staff)->post(route('farms.cows.weights.store', [$farm, $cow]), [
            'weight_kg' => 250.5,
            'measured_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('weight_records', ['cow_id' => $cow->id, 'created_by' => $staff->id]);
    }

    public function test_invalid_weight_is_rejected(): void
    {
        [$owner, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);

        $this->actingAs($owner)->post(route('farms.cows.weights.store', [$farm, $cow]), [
            'weight_kg' => -5,
            'measured_at' => now()->toDateString(),
        ])->assertSessionHasErrors('weight_kg');

        $this->actingAs($owner)->post(route('farms.cows.weights.store', [$farm, $cow]), [
            'weight_kg' => 300,
            'measured_at' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('measured_at');
    }

    public function test_only_manager_or_owner_can_delete_weight(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $cow = Cow::factory()->create(['farm_id' => $farm->id]);
        $record = new WeightRecord(['weight_kg' => 200, 'measured_at' => now()->toDateString()]);
        $record->forceFill(['farm_id' => $farm->id, 'cow_id' => $cow->id])->save();

        $this->actingAs($staff)
            ->delete(route('farms.cows.weights.destroy', [$farm, $cow, $record]))
            ->assertForbidden();

        $this->actingAs($farm->owner)
            ->delete(route('farms.cows.weights.destroy', [$farm, $cow, $record]))
            ->assertRedirect();

        $this->assertSoftDeleted('weight_records', ['id' => $record->id]);
    }

    public function test_photo_is_stored(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('ছবির test-এর জন্য PHP-তে GD extension চালু থাকতে হবে।');
        }

        Storage::fake('public');
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)->post(route('farms.cows.store', $farm), $this->cowData([
            'photo' => UploadedFile::fake()->image('cow.jpg', 300, 300),
        ]))->assertSessionHasNoErrors();

        $cow = Cow::withoutGlobalScopes()->firstOrFail();
        $this->assertNotNull($cow->photo_path);
        Storage::disk('public')->assertExists($cow->photo_path);
    }
}
