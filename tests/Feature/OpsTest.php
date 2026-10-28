<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\QueryMonitor;
use Database\Seeders\PlanSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PlanSeeder::class);
    }

    private function farmWith(): array
    {
        $owner = User::factory()->create();
        $farm = Farm::factory()->create(['owner_user_id' => $owner->id]);
        $farm->users()->attach($owner->id, ['role' => 'owner']);
        app(SubscriptionService::class)->startTrial($farm);

        return [$owner, $farm->fresh()];
    }

    public function test_where_date_between_covers_both_boundary_days(): void
    {
        [, $farm] = $this->farmWith();

        foreach (['2026-09-30', '2026-10-01', '2026-10-10', '2026-10-11'] as $date) {
            (new Expense())->forceFill([
                'farm_id' => $farm->id,
                'category' => 'feed',
                'expense_date' => $date,
                'total_amount' => 100,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'status' => 'active',
                'source' => 'manual',
            ])->save();
        }

        $this->assertSame(2, Expense::withoutGlobalScopes()->whereDateBetween('expense_date', '2026-10-01', '2026-10-10')->count());
        $this->assertSame(2, Expense::withoutGlobalScopes()
            ->whereDateBetween('expense_date', Carbon::parse('2026-10-01'), Carbon::parse('2026-10-10 18:30'))->count());
        $this->assertSame(1, Expense::withoutGlobalScopes()->whereDateBetween('expense_date', '2026-10-10', '2026-10-10')->count());
    }

    public function test_only_slow_queries_are_logged_and_without_their_values(): void
    {
        config(['ops.slow_query_ms' => 100]);
        Log::spy();

        QueryMonitor::handle(new QueryExecuted('select * from cows where id = ?', ['SECRET-BINDING'], 10.0, DB::connection()));
        Log::shouldNotHaveReceived('warning');

        QueryMonitor::handle(new QueryExecuted('select * from cows where id = ?', ['SECRET-BINDING'], 250.0, DB::connection()));

        Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) {
            return $message === 'slow query' && ! str_contains(json_encode($context), 'SECRET-BINDING');
        });
    }

    public function test_perf_seed_builds_data_and_refuses_to_run_in_production(): void
    {
        Artisan::call('perf:seed', ['--cows' => 3, '--days' => 4]);

        $farm = Farm::withTrashed()->where('name', 'PERF খামার')->firstOrFail();

        $this->assertSame(12, DB::table('milk_records')->where('farm_id', $farm->id)->count());
        $this->assertSame(4, DB::table('sales')->where('farm_id', $farm->id)->count());
        $this->assertSame(12, DB::table('expenses')->where('farm_id', $farm->id)->count());

        $before = Farm::withTrashed()->count();
        $this->app->detectEnvironment(fn() => 'production');

        $this->assertSame(1, Artisan::call('perf:seed', ['--cows' => 3, '--days' => 4]));
        $this->assertSame($before, Farm::withTrashed()->count());
    }

    public function test_perf_report_times_every_report(): void
    {
        Artisan::call('perf:seed', ['--cows' => 3, '--days' => 4]);
        $farm = Farm::withTrashed()->where('name', 'PERF খামার')->firstOrFail();

        $code = Artisan::call('perf:report', ['farm' => $farm->id, '--period' => 'this_month']);
        $output = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('cashFlow', $output);
        $this->assertStringContainsString('profitLoss', $output);
        $this->assertStringContainsString('cowProfitability', $output);
    }

    public function test_ops_check_reads_the_heartbeat_and_the_newest_backup(): void
    {
        Storage::fake('local');
        config(['backup.backup.name' => 'genz', 'backup.backup.destination.disks' => ['local']]);

        // সব ঠিক: cron চলছে, নতুন ব্যাকআপ আছে
        Cache::put('scheduler:heartbeat', now()->timestamp, 3600);
        Storage::disk('local')->put('genz/2026-10-27-02-00-00.zip', 'x');

        $this->assertSame(0, Artisan::call('ops:check'));
        $good = Artisan::output();
        $this->assertStringContainsString('scheduler চলছে', $good);
        $this->assertStringNotContainsString('চলছে না', $good);
        $this->assertStringContainsString('ব্যাকআপ আছে', $good);

        // cron বন্ধ
        Cache::forget('scheduler:heartbeat');
        $this->assertSame(1, Artisan::call('ops:check'));
        $this->assertStringContainsString('scheduler চলছে না', Artisan::output());

        // ব্যাকআপ পুরোনো
        Cache::put('scheduler:heartbeat', now()->timestamp, 3600);
        touch(Storage::disk('local')->path('genz/2026-10-27-02-00-00.zip'), time() - 4 * 86400);

        $this->assertSame(1, Artisan::call('ops:check'));
        $this->assertStringContainsString('ব্যাকআপ পুরোনো', Artisan::output());
    }

    public function test_the_scheduler_has_the_jobs_that_keep_the_system_healthy(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        foreach (['backup:run', 'backup:clean', 'backup:monitor', 'audit:prune', 'sms:send-reminders', 'subscriptions:sync'] as $command) {
            $this->assertStringContainsString($command, $output);
        }
    }

    public function test_photos_sanitize_rewrites_old_photos_once(): void
    {
        Storage::fake('public');
        [, $farm] = $this->farmWith();

        $canvas = imagecreatetruecolor(800, 600);
        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        $legacy = "cows/{$farm->id}/legacy.png";
        Storage::disk('public')->put($legacy, $png);
        $cow = Cow::factory()->create(['farm_id' => $farm->id, 'photo_path' => $legacy]);

        // --dry কিছু বদলায় না
        Artisan::call('photos:sanitize', ['--dry' => true]);
        $this->assertSame($legacy, $cow->fresh()->photo_path);
        Storage::disk('public')->assertExists($legacy);

        Artisan::call('photos:sanitize');
        $cow->refresh();

        $this->assertStringStartsWith("cows/{$farm->id}/c_", $cow->photo_path);
        $this->assertStringEndsWith('.jpg', $cow->photo_path);
        Storage::disk('public')->assertExists($cow->photo_path);
        Storage::disk('public')->assertMissing($legacy);

        // আবার চালালে একই ছবি আবার এনকোড হয় না
        $before = $cow->photo_path;
        Artisan::call('photos:sanitize');
        $this->assertSame($before, $cow->fresh()->photo_path);
    }
}
