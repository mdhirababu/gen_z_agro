<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cow;
use App\Models\Farm;
use App\Models\Sale;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Audit;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuditTest extends TestCase
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

    private function spend(User $user, Farm $farm, array $override = []): void
    {
        $this->actingAs($user)->post(route('farms.expenses.store', $farm), array_merge([
            'category' => 'feed',
            'expense_date' => now()->toDateString(),
            'total_amount' => 700,
            'pay_mode' => 'unpaid',
            'pay_method' => 'cash',
        ], $override))->assertSessionHasNoErrors();
    }

    private function sell(User $user, Farm $farm, array $override = []): void
    {
        $this->actingAs($user)->post(route('farms.sales.store', $farm), array_merge([
            'sale_type' => 'milk',
            'sold_at' => now()->toDateString(),
            'quantity' => 20,
            'unit_price' => 60,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ], $override))->assertSessionHasNoErrors();
    }

    public function test_a_new_expense_is_logged_with_who_where_and_what(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->spend($owner, $farm);

        $log = AuditLog::where('auditable_type', 'Expense')->where('event', 'created')->firstOrFail();

        $this->assertSame($farm->id, (int) $log->farm_id);
        $this->assertSame($owner->id, (int) $log->user_id);
        $this->assertSame($owner->name, $log->actor_name);
        $this->assertSame('web', $log->source);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('feed', $log->new_values['category']);
        $this->assertArrayNotHasKey('updated_at', $log->new_values);
    }

    public function test_voiding_a_sale_is_logged_as_voided(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->sell($owner, $farm);

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($owner)->post(route('farms.sales.void', [$farm, $sale]), ['reason' => 'ভুল বিক্রি'])
            ->assertSessionHas('success');

        $log = AuditLog::where('auditable_type', 'Sale')->where('event', 'voided')->firstOrFail();

        $this->assertSame('active', $log->old_values['status']);
        $this->assertNotSame('active', $log->new_values['status']);
        $this->assertSame($owner->id, (int) $log->user_id);
    }

    public function test_only_tracked_changes_are_logged_and_console_changes_have_no_actor(): void
    {
        [, $farm] = $this->farmWith();
        $cow = Cow::factory()->create(['farm_id' => $farm->id, 'name' => 'পুরোনো']);

        DB::table('audit_logs')->delete();

        $cow->touch(); // শুধু updated_at বদলায়
        $this->assertSame(0, AuditLog::count());

        $cow->update(['name' => 'নতুন']);

        $log = AuditLog::where('auditable_type', 'Cow')->where('event', 'updated')->firstOrFail();

        $this->assertSame('পুরোনো', $log->old_values['name']);
        $this->assertSame('নতুন', $log->new_values['name']);
        $this->assertSame('console', $log->source);
        $this->assertNull($log->user_id);
        $this->assertNull($log->ip_address);
    }

    public function test_secrets_never_reach_the_log(): void
    {
        Audit::log('updated', 1, 'Farm', 1, 'পরীক্ষা', ['password' => 'a'], [
            'password' => 'b',
            'remember_token' => 't',
            'name' => 'ঠিক আছে',
        ]);

        $log = AuditLog::latest('id')->firstOrFail();

        $this->assertSame(['name' => 'ঠিক আছে'], $log->new_values);
        $this->assertNull($log->old_values);
    }

    public function test_a_log_can_not_be_changed_or_deleted(): void
    {
        Audit::log('created', 1, 'Farm', 1, 'পরীক্ষা');
        $log = AuditLog::firstOrFail();

        try {
            $log->update(['event' => 'deleted']);
            $this->fail('বদলানো যাওয়া উচিত নয়');
        } catch (\LogicException) {
            $this->assertSame('created', $log->fresh()->event);
        }

        try {
            $log->delete();
            $this->fail('মোছা যাওয়া উচিত নয়');
        } catch (\LogicException) {
            $this->assertNotNull(AuditLog::find($log->id));
        }
    }

    public function test_prune_removes_only_logs_older_than_the_retention(): void
    {
        config(['audit.retention_months' => 1]);

        $old = AuditLog::create([
            'event' => 'created',
            'auditable_type' => 'Farm',
            'source' => 'web',
            'created_at' => now()->subMonths(2),
        ]);
        $recent = AuditLog::create([
            'event' => 'created',
            'auditable_type' => 'Farm',
            'source' => 'web',
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('audit:prune')->assertExitCode(0);

        $this->assertNull(AuditLog::find($old->id));
        $this->assertNotNull(AuditLog::find($recent->id));
    }

    public function test_only_the_owner_sees_the_page_and_only_their_own_farm(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$otherOwner, $other] = $this->farmWith();

        $this->spend($owner, $farm);
        $this->sell($owner, $farm);
        $this->spend($otherOwner, $other, ['total_amount' => 999]);

        $this->actingAs($owner)->get(route('farms.audit.index', $farm))
            ->assertOk()
            ->assertViewHas('logs', fn($logs) => $logs->count() > 0 && $logs->every(fn($l) => (int) $l->farm_id === $farm->id));

        $this->actingAs($owner)->get(route('farms.audit.index', [$farm, 'type' => 'Sale']))
            ->assertViewHas('logs', fn($logs) => $logs->count() > 0 && $logs->every(fn($l) => $l->auditable_type === 'Sale'));

        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $farm->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($manager)->get(route('farms.audit.index', $farm))->assertForbidden();
        $this->actingAs($staff)->get(route('farms.audit.index', $farm))->assertForbidden();

        $status = $this->actingAs($owner)->get(route('farms.audit.index', $other))->status();
        $this->assertContains($status, [403, 404]);
    }

    public function test_member_changes_are_logged(): void
    {
        [, $farm] = $this->farmWith();
        $member = User::factory()->create();

        $farm->users()->attach($member->id, ['role' => 'staff']);

        $created = AuditLog::where('auditable_type', 'FarmMember')->where('event', 'created')
            ->get()->first(fn($l) => (int) ($l->new_values['user_id'] ?? 0) === $member->id);
        $this->assertNotNull($created);
        $this->assertSame('staff', $created->new_values['role']);
        $this->assertSame($farm->id, (int) $created->farm_id);

        $farm->users()->updateExistingPivot($member->id, ['role' => 'manager']);

        $updated = AuditLog::where('auditable_type', 'FarmMember')->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('staff', $updated->old_values['role']);
        $this->assertSame('manager', $updated->new_values['role']);

        $farm->users()->detach($member->id);

        $this->assertTrue(AuditLog::where('auditable_type', 'FarmMember')->where('event', 'deleted')->exists());
    }

    public function test_report_downloads_are_logged(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)
            ->get(route('farms.reports.export', [$farm, 'cash-flow', 'xlsx', 'r' => 'today']))
            ->assertOk();

        $log = AuditLog::where('event', 'exported')->firstOrFail();

        $this->assertSame('Report', $log->auditable_type);
        $this->assertSame('cash-flow', $log->meta['report']);
        $this->assertSame('xlsx', $log->meta['format']);
        $this->assertSame($owner->id, (int) $log->user_id);
    }

    public function test_audit_show_command_lists_logs(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->spend($owner, $farm);

        Artisan::call('audit:show', ['--farm' => $farm->id]);

        $this->assertStringContainsString('খরচ', Artisan::output());
    }

    public function test_logging_can_be_switched_off(): void
    {
        [$owner, $farm] = $this->farmWith();

        config(['audit.enabled' => false]);
        $before = AuditLog::count();

        $this->spend($owner, $farm);

        $this->assertSame($before, AuditLog::count());
    }
}
