<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\SmsService;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsTest extends TestCase
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

    private function enableSms(Farm $farm, string $phone = '01712345678'): Farm
    {
        $farm->forceFill(['sms_enabled' => true, 'sms_phone' => $phone])->save();

        return $farm->fresh();
    }

    private function due(Farm $farm, string $tag, int $days): HealthEvent
    {
        $cow = Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => $tag]);

        $e = new HealthEvent();
        $e->forceFill([
            'farm_id' => $farm->id,
            'cow_id' => $cow->id,
            'kind' => 'vaccination',
            'event_date' => now()->subDays(100)->toDateString(),
            'vaccine_code' => 'other',
            'title' => 'টিকা',
            'next_due_date' => now()->addDays($days)->toDateString(),
            'follow_up_status' => 'open',
            'source' => 'manual',
        ])->save();

        return $e;
    }

    private function useHttpDriver(string $successContains = 'OK'): void
    {
        config([
            'sms.driver' => 'http',
            'sms.http.url' => 'https://sms.test/send',
            'sms.http.method' => 'POST',
            'sms.http.body_format' => 'form',
            'sms.http.fields' => ['to' => 'to', 'message' => 'msg'],
            'sms.http.static' => ['api_key' => 'K1'],
            'sms.http.success_contains' => $successContains,
        ]);
    }

    public function test_phone_numbers_are_normalized(): void
    {
        $this->assertSame('8801712345678', SmsService::normalize('01712345678'));
        $this->assertSame('8801712345678', SmsService::normalize('+8801712345678'));
        $this->assertSame('8801712345678', SmsService::normalize('8801712345678'));
        $this->assertSame('01712345678', SmsService::local('+88 017-1234-5678'));
        $this->assertNull(SmsService::normalize('12345'));
        $this->assertNull(SmsService::normalize('01212345678'));
        $this->assertNull(SmsService::normalize(null));
    }

    public function test_only_the_owner_can_change_sms_settings(): void
    {
        [$owner, $farm] = $this->farmWith();
        $manager = User::factory()->create();
        $staff = User::factory()->create();
        $farm->users()->attach($manager->id, ['role' => 'manager']);
        $farm->users()->attach($staff->id, ['role' => 'staff']);

        $payload = ['sms_enabled' => 1, 'sms_phone' => '01712345678'];

        $this->actingAs($manager)->put(route('farms.notifications.update', $farm), $payload)->assertForbidden();
        $this->actingAs($staff)->put(route('farms.notifications.update', $farm), $payload)->assertForbidden();

        $this->actingAs($owner)->put(route('farms.notifications.update', $farm), $payload)->assertSessionHasNoErrors();

        $farm->refresh();
        $this->assertTrue($farm->sms_enabled);
        $this->assertSame('01712345678', $farm->sms_phone);
    }

    public function test_invalid_number_is_rejected_and_enabling_needs_a_usable_number(): void
    {
        [$owner, $farm] = $this->farmWith();
        $farm->forceFill(['phone' => null])->save();

        $this->actingAs($owner)->put(route('farms.notifications.update', $farm), ['sms_enabled' => 1, 'sms_phone' => '12345'])
            ->assertSessionHasErrors('sms_phone');

        $this->actingAs($owner)->put(route('farms.notifications.update', $farm), ['sms_enabled' => 1])
            ->assertSessionHasErrors('sms_phone');

        $this->assertFalse($farm->fresh()->sms_enabled);
    }

    public function test_command_sends_one_digest_for_tasks_in_the_reminder_windows(): void
    {
        [, $farm] = $this->farmWith();
        $this->enableSms($farm);

        $this->due($farm, 'CW-A', 3);
        $this->due($farm, 'CW-B', 0);
        $this->due($farm, 'CW-C', -1);
        $this->due($farm, 'CW-D', 2);   // জানানোর দিন নয়
        $this->due($farm, 'CW-E', -5);  // অনেক দেরি, আর জানানো হয় না

        $this->artisan('sms:send-reminders')->assertExitCode(0);

        $this->assertEquals(1, SmsLog::count());

        $log = SmsLog::first();
        $this->assertEquals('sent', $log->status);
        $this->assertEquals('01712345678', $log->phone);
        $this->assertStringContainsString('CW-A', $log->message);
        $this->assertStringContainsString('CW-B', $log->message);
        $this->assertStringContainsString('CW-C', $log->message);
        $this->assertStringNotContainsString('CW-D', $log->message);
        $this->assertStringNotContainsString('CW-E', $log->message);
    }

    public function test_digest_is_sent_only_once_a_day(): void
    {
        [, $farm] = $this->farmWith();
        $this->enableSms($farm);
        $this->due($farm, 'CW-A', 0);

        $this->artisan('sms:send-reminders')->assertExitCode(0);
        $this->artisan('sms:send-reminders')->assertExitCode(0);

        $this->assertEquals(1, SmsLog::count());
    }

    public function test_nothing_is_sent_when_nothing_is_due_or_sms_is_off_or_farm_expired(): void
    {
        [, $quiet] = $this->farmWith();
        $this->enableSms($quiet);
        $this->due($quiet, 'FAR-1', 10);

        [, $off] = $this->farmWith();
        $this->due($off, 'OFF-1', 0);

        [, $expired] = $this->farmWith();
        $expired = $this->enableSms($expired);
        $this->due($expired, 'EXP-1', 0);
        $expired->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->artisan('sms:send-reminders')->assertExitCode(0);

        $this->assertEquals(0, SmsLog::count());
    }

    public function test_http_driver_posts_to_the_gateway_and_detects_success(): void
    {
        [, $farm] = $this->farmWith();
        $this->enableSms($farm);
        $this->due($farm, 'CW-A', 0);

        $this->useHttpDriver('OK');
        Http::fake(['sms.test/*' => Http::response('OK-123', 200)]);

        $this->artisan('sms:send-reminders')->assertExitCode(0);

        Http::assertSent(fn($request) => $request['to'] === '8801712345678'
            && $request['api_key'] === 'K1'
            && str_contains($request['msg'], 'CW-A'));

        $log = SmsLog::first();
        $this->assertEquals('sent', $log->status);
        $this->assertEquals('OK-123', $log->provider_response);
    }

    public function test_failed_digest_is_retried_up_to_three_times(): void
    {
        [, $farm] = $this->farmWith();
        $this->enableSms($farm);
        $this->due($farm, 'CW-A', 0);

        $this->useHttpDriver('OK');
        Http::fake(['sms.test/*' => Http::response('ERROR', 200)]);

        foreach (range(1, 4) as $_) {
            $this->artisan('sms:send-reminders')->assertExitCode(0);
        }

        $log = SmsLog::firstOrFail();
        $this->assertEquals('failed', $log->status);
        $this->assertEquals(3, $log->attempts);
        $this->assertEquals(1, SmsLog::count());
        Http::assertSentCount(3);
    }

    public function test_monthly_segment_limit_stops_sending(): void
    {
        [, $farm] = $this->farmWith();
        $this->enableSms($farm);
        $this->due($farm, 'CW-A', 0);

        config(['sms.monthly_segment_limit' => 1]);

        $old = new SmsLog();
        $old->forceFill([
            'farm_id' => $farm->id,
            'phone' => '01712345678',
            'kind' => 'test',
            'message' => 'x',
            'segments' => 1,
            'status' => 'sent',
            'attempts' => 1,
        ])->save();

        $this->artisan('sms:send-reminders')->assertExitCode(0);

        $digest = SmsLog::where('kind', 'task_digest')->firstOrFail();
        $this->assertEquals('skipped', $digest->status);
    }

    public function test_gateway_errors_do_not_leak_secrets(): void
    {
        [, $farm] = $this->farmWith();
        $this->enableSms($farm);
        $this->due($farm, 'CW-A', 0);

        $this->useHttpDriver('OK');
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: https://sms.test/send?api_key=SECRET-KEY');
        });

        $this->artisan('sms:send-reminders')->assertExitCode(0);

        $log = SmsLog::firstOrFail();
        $this->assertEquals('failed', $log->status);
        $this->assertStringNotContainsString('SECRET-KEY', (string) $log->error);
        $this->assertStringNotContainsString('SECRET-KEY', (string) $log->provider_response);
    }

    public function test_test_sms_is_limited_to_three_per_day(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->enableSms($farm);

        foreach (range(1, 3) as $_) {
            $this->actingAs($owner)->post(route('farms.notifications.test', $farm))->assertSessionHas('success');
        }

        $this->actingAs($owner)->post(route('farms.notifications.test', $farm))->assertSessionHas('error');

        $this->assertEquals(3, SmsLog::where('kind', 'test')->count());
    }
}
