<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // test-এ Vite build ছাড়াই view চলবে
        $this->seed(PlanSeeder::class);
    }

    private function farmWithMember(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['owner_user_id' => $user->id]);
        $farm->users()->attach($user->id, ['role' => $role]);
        app(SubscriptionService::class)->startTrial($farm);

        return [$user, $farm];
    }

    public function test_member_can_open_own_farm_dashboard(): void
    {
        [$user, $farm] = $this->farmWithMember();

        $this->actingAs($user)
            ->get(route('farms.dashboard', $farm))
            ->assertOk();
    }

    public function test_user_cannot_open_another_farms_dashboard(): void
    {
        [$userA] = $this->farmWithMember();
        [, $farmB] = $this->farmWithMember();

        $this->actingAs($userA)
            ->get(route('farms.dashboard', $farmB))
            ->assertNotFound();
    }

    public function test_staff_cannot_update_farm(): void
    {
        [, $farm] = $this->farmWithMember();
        $staff = User::factory()->create();
        $farm->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff)
            ->put(route('farms.update', $farm), ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertNotEquals('Hacked', $farm->fresh()->name);
    }

    public function test_owner_can_update_farm(): void
    {
        [$owner, $farm] = $this->farmWithMember();

        $this->actingAs($owner)
            ->put(route('farms.update', $farm), ['name' => 'New Name'])
            ->assertRedirect();

        $this->assertEquals('New Name', $farm->fresh()->name);
    }

    public function test_creating_farm_attaches_creator_as_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('farms.store'), ['name' => 'My Farm'])
            ->assertRedirect();

        $farm = Farm::where('name', 'My Farm')->firstOrFail();
        $this->assertEquals('owner', $farm->roleOf($user));
    }

    public function test_owner_cannot_be_demoted_via_members_form(): void
    {
        [$owner, $farm] = $this->farmWithMember();

        $this->actingAs($owner)
            ->post(route('farms.members.store', $farm), [
                'email' => $owner->email,
                'role' => 'staff',
            ])
            ->assertSessionHasErrors('email');

        $this->assertEquals('owner', $farm->roleOf($owner));
    }
}
