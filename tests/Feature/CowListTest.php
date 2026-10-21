<?php

namespace Tests\Feature;

use App\Livewire\CowList;
use App\Models\Cow;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CowListTest extends TestCase
{
    use RefreshDatabase;

    private function farmFor(User $user): Farm
    {
        $farm = Farm::factory()->create(['owner_user_id' => $user->id]);
        $farm->users()->attach($user->id, ['role' => 'owner']);

        return $farm;
    }

    public function test_member_sees_only_own_farm_cows_and_can_search(): void
    {
        $user = User::factory()->create();
        $farm = $this->farmFor($user);
        $otherOwner = User::factory()->create();
        $otherFarm = $this->farmFor($otherOwner);

        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'ALPHA1']);
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'BETA2']);
        Cow::factory()->create(['farm_id' => $otherFarm->id, 'tag_number' => 'OTHER9']);

        Livewire::actingAs($user)
            ->test(CowList::class, ['farmId' => $farm->id])
            ->assertSee('ALPHA1')
            ->assertSee('BETA2')
            ->assertDontSee('OTHER9')
            ->set('search', 'ALPHA')
            ->assertSee('ALPHA1')
            ->assertDontSee('BETA2');
    }

    public function test_non_member_cannot_load_list(): void
    {
        $owner = User::factory()->create();
        $farm = $this->farmFor($owner);
        $outsider = User::factory()->create();

        Livewire::actingAs($outsider)
            ->test(CowList::class, ['farmId' => $farm->id])
            ->assertForbidden();
    }
}
