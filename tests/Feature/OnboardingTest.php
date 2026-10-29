<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\MilkRecord;
use App\Models\User;
use App\Services\OnboardingService;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
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
        $farm->forceFill(['phone' => null, 'address' => null])->save();
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        app(SubscriptionService::class)->startTrial($farm);

        if ($role === 'owner') {
            return [$owner, $farm->fresh()];
        }

        $member = User::factory()->create();
        $farm->users()->attach($member->id, ['role' => $role]);

        return [$member, $farm->fresh()];
    }

    private function onboarding(Farm $farm, User $user): array
    {
        app()->forgetInstance('currentFarm'); // আগের request-এর খামার বাঁধা থাকলে সরানো

        return app(OnboardingService::class)->for($farm->fresh(), $user);
    }

    private function keys(array $onboarding): array
    {
        return array_column($onboarding['steps'], 'key');
    }

    private function milkingCow(Farm $farm, array $override = []): Cow
    {
        return Cow::factory()->create(array_merge([
            'farm_id' => $farm->id,
            'category' => 'milking',
            'sex' => 'female',
            'status' => 'active',
        ], $override));
    }

    public function test_a_new_owner_starts_at_the_cows_step_with_dependent_steps_locked(): void
    {
        [$owner, $farm] = $this->farmWith();

        $o = $this->onboarding($farm, $owner);

        $this->assertTrue($o['visible']);
        $this->assertSame(['cows', 'milk', 'expense', 'sale', 'health', 'profile', 'members', 'sms'], $this->keys($o));
        $this->assertSame(0, $o['done']);
        $this->assertSame('cows', $o['next']);

        $steps = array_column($o['steps'], null, 'key');
        $this->assertTrue($steps['milk']['locked']);
        $this->assertTrue($steps['health']['locked']);
        $this->assertFalse($steps['expense']['locked']);
        $this->assertNotNull($steps['cows']['alt']); // মালিকের জন্য Excel ইমপোর্টের পথ
    }

    public function test_progress_follows_the_real_data(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->milkingCow($farm);
        $o = $this->onboarding($farm, $owner);
        $this->assertSame('milk', $o['next']);
        $this->assertSame(1, $o['done']);

        (new MilkRecord())->forceFill([
            'farm_id' => $farm->id,
            'cow_id' => Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->value('id'),
            'milk_date' => now()->toDateString(),
            'session_1' => 5,
            'session_2' => 3,
            'session_3' => 0,
            'source' => 'manual',
        ])->save();

        $o = $this->onboarding($farm, $owner);
        $this->assertSame('expense', $o['next']);

        (new Expense())->forceFill([
            'farm_id' => $farm->id,
            'category' => 'feed',
            'expense_date' => now()->toDateString(),
            'total_amount' => 100,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'active',
            'source' => 'manual',
        ])->save();

        $o = $this->onboarding($farm, $owner);
        $this->assertSame('sale', $o['next']);
        $this->assertSame(3, $o['done']);
        $this->assertSame(8, $o['total']);
        $this->assertSame(38, $o['percent']);
        $this->assertFalse($o['core_done']);
    }

    public function test_staff_only_sees_the_steps_they_can_do(): void
    {
        [$staff, $farm] = $this->farmWith('staff');

        $o = $this->onboarding($farm, $staff);

        $this->assertSame(['cows', 'milk', 'health'], $this->keys($o));
        $this->assertNull($o['steps'][0]['alt']); // স্টাফ ইমপোর্ট করতে পারে না
    }

    public function test_manager_sees_money_steps_but_not_owner_only_steps(): void
    {
        [$manager, $farm] = $this->farmWith('manager');

        $keys = $this->keys($this->onboarding($farm, $manager));

        $this->assertContains('expense', $keys);
        $this->assertContains('sale', $keys);
        $this->assertNotContains('members', $keys);
        $this->assertNotContains('sms', $keys);
    }

    public function test_the_milk_step_is_hidden_when_there_is_no_milking_cow(): void
    {
        [$staff, $farm] = $this->farmWith('staff');

        Cow::factory()->create(['farm_id' => $farm->id, 'category' => 'calf', 'sex' => 'female', 'status' => 'active']);

        $this->assertSame(['cows', 'health'], $this->keys($this->onboarding($farm, $staff)));
    }

    public function test_dismissing_is_personal_and_can_be_undone(): void
    {
        [$manager, $farm] = $this->farmWith('manager');
        $owner = User::findOrFail($farm->owner_user_id);

        $this->actingAs($owner)->post(route('farms.onboarding.dismiss', $farm))
            ->assertRedirect(route('farms.dashboard', $farm));

        $mine = $this->onboarding($farm, $owner);
        $this->assertFalse($mine['visible']);
        $this->assertTrue($mine['restorable']);

        // ম্যানেজারের পাতা থেকে যায় না
        $this->assertTrue($this->onboarding($farm, $manager)['visible']);

        $this->actingAs($owner)->post(route('farms.onboarding.restore', $farm));
        $this->assertTrue($this->onboarding($farm, $owner)['visible']);
    }

    public function test_it_disappears_when_everything_is_done_or_the_farm_is_read_only(): void
    {
        [$staff, $farm] = $this->farmWith('staff');

        $cow = $this->milkingCow($farm);
        (new MilkRecord())->forceFill([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'milk_date' => now()->toDateString(),
            'session_1' => 5,
            'session_2' => 3,
            'session_3' => 0,
            'source' => 'manual',
        ])->save();
        (new HealthEvent())->forceFill([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'kind' => 'vaccination',
            'event_date' => now()->toDateString(),
            'vaccine_code' => 'other',
            'title' => 'টিকা',
            'next_due_date' => now()->addDays(30)->toDateString(),
            'follow_up_status' => 'open',
            'source' => 'manual',
        ])->save();

        $o = $this->onboarding($farm, $staff);
        $this->assertTrue($o['all_done']);
        $this->assertFalse($o['visible']);
        $this->assertFalse($o['restorable']);

        // মেয়াদ শেষ হলে নতুন খামারেও দেখায় না
        [$owner, $expired] = $this->farmWith();
        $expired->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->assertFalse($this->onboarding($expired, $owner)['visible']);
    }

    public function test_other_farms_data_does_not_count(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();

        $this->milkingCow($other);
        (new Expense())->forceFill([
            'farm_id' => $other->id,
            'category' => 'feed',
            'expense_date' => now()->toDateString(),
            'total_amount' => 100,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'active',
            'source' => 'manual',
        ])->save();

        $o = $this->onboarding($farm, $owner);

        $this->assertSame(0, $o['done']);
        $this->assertSame('cows', $o['next']);
    }

    public function test_only_members_can_dismiss(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$outsider] = $this->farmWith();

        $status = $this->actingAs($outsider)->post(route('farms.onboarding.dismiss', $farm))->status();

        $this->assertContains($status, [403, 404]);
        $this->assertTrue($this->onboarding($farm, $owner)['visible']);
    }

    public function test_the_dashboard_shows_the_guide_and_keeps_the_empty_cow_message(): void
    {
        [$owner, $farm] = $this->farmWith();

        // formatter লাইন ভাঙলেও যেন না ভাঙে: সব ফাঁকা ও নতুন লাইন এক ফাঁকায় এনে মেলানো
        $text = fn($response) => preg_replace('/\s+/u', ' ', $response->getContent());

        $page = $text($this->actingAs($owner)->get(route('farms.dashboard', $farm))->assertOk());
        $this->assertStringContainsString('শুরু করা যাক', $page);
        $this->assertStringContainsString('এখনো কোনো গরু যোগ করা হয়নি', $page);

        $this->actingAs($owner)->post(route('farms.onboarding.dismiss', $farm));

        $page = $text($this->actingAs($owner)->get(route('farms.dashboard', $farm))->assertOk());
        $this->assertStringNotContainsString('শুরু করা যাক', $page);
        $this->assertStringContainsString('শুরুর গাইড আবার দেখান', $page);
    }

    public function test_the_empty_cow_list_offers_the_import_to_owner_and_manager_only(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)->get(route('farms.cows.index', $farm))
            ->assertOk()
            ->assertSee('Excel থেকে ইমপোর্ট');

        $staff = User::factory()->create();
        $farm->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff)->get(route('farms.cows.index', $farm))
            ->assertOk()
            ->assertSee('নতুন গরু যোগ করুন')
            ->assertDontSee('Excel থেকে ইমপোর্ট');
    }
}
