<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cow;
use App\Models\Farm;
use App\Models\Sale;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class DataExportTest extends TestCase
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

    private function fetch(User $user, Farm $farm, array $query = [])
    {
        return $this->actingAs($user)->get(route('farms.data-export.download', [$farm] + $query));
    }

    private function zipOf($response): ZipArchive
    {
        $copy = tempnam(sys_get_temp_dir(), 'zip');
        copy($response->baseResponse->getFile()->getPathname(), $copy);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($copy) === true);

        return $zip;
    }

    public function test_the_owner_gets_all_of_their_own_data_including_voided_records(): void
    {
        [$owner, $farm] = $this->farmWith();
        [, $other] = $this->farmWith();

        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'MINE-1']);
        Cow::factory()->create(['farm_id' => $other->id, 'tag_number' => 'THEIRS-1']);

        $this->actingAs($owner)->post(route('farms.sales.store', $farm), [
            'sale_type' => 'milk',
            'sold_at' => now()->toDateString(),
            'quantity' => 10,
            'unit_price' => 60,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->actingAs($owner)->post(route('farms.sales.void', [$farm, $sale]), ['reason' => 'ভুল বিক্রি']);

        $response = $this->fetch($owner, $farm)->assertOk();
        $this->assertStringContainsString('application/zip', $response->headers->get('Content-Type'));

        $zip = $this->zipOf($response);

        $cows = $zip->getFromName('cows.csv');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $cows); // BOM, Excel-এ বাংলা ঠিক দেখানোর জন্য
        $this->assertStringContainsString('MINE-1', $cows);
        $this->assertStringNotContainsString('THEIRS-1', $cows);

        $sales = $zip->getFromName('sales.csv');
        $this->assertStringContainsString('void', $sales);          // বাতিল রেকর্ডও আছে
        $this->assertStringContainsString('ভুল বিক্রি', $sales);

        $this->assertStringContainsString($owner->email, $zip->getFromName('members.csv'));
        $this->assertStringContainsString($farm->name, $zip->getFromName('README.txt'));
        $this->assertNotFalse($zip->getFromName('audit_logs.csv'));
    }

    public function test_spreadsheet_formulas_in_user_text_are_neutralised(): void
    {
        [$owner, $farm] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'F-1', 'name' => '=1+1']);

        $cows = $this->zipOf($this->fetch($owner, $farm)->assertOk())->getFromName('cows.csv');

        $this->assertStringContainsString("'=1+1", $cows);
    }

    public function test_only_the_owner_of_that_farm_can_download(): void
    {
        [$manager, $farm] = $this->farmWith('manager');
        [$staff] = [User::factory()->create()];
        $farm->users()->attach($staff->id, ['role' => 'staff']);
        [$otherOwner] = $this->farmWith();

        $this->fetch($manager, $farm)->assertForbidden();
        $this->fetch($staff, $farm)->assertForbidden();
        $this->assertContains($this->fetch($otherOwner, $farm)->status(), [403, 404]);
    }

    public function test_an_expired_farm_can_still_download_its_data(): void
    {
        [$owner, $farm] = $this->farmWith();
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->fetch($owner, $farm)->assertOk();
    }

    public function test_the_download_is_limited_to_three_per_hour(): void
    {
        [$owner, $farm] = $this->farmWith();

        foreach (range(1, 3) as $_) {
            $this->fetch($owner, $farm)->assertOk();
        }

        $this->fetch($owner, $farm)->assertStatus(429);
    }

    public function test_photos_are_only_included_when_asked_for(): void
    {
        Storage::fake('public');
        [$owner, $farm] = $this->farmWith();

        Storage::disk('public')->put("cows/{$farm->id}/c_x.jpg", 'fake-image');
        $cow = Cow::factory()->create(['farm_id' => $farm->id, 'photo_path' => "cows/{$farm->id}/c_x.jpg"]);

        $without = $this->zipOf($this->fetch($owner, $farm)->assertOk());
        $this->assertFalse($without->locateName("photos/cow-{$cow->id}-c_x.jpg"));
        $this->assertStringContainsString('photo_path', $without->getFromName('cows.csv'));

        $with = $this->zipOf($this->fetch($owner, $farm, ['photos' => 1])->assertOk());
        $this->assertNotFalse($with->locateName("photos/cow-{$cow->id}-c_x.jpg"));
    }

    public function test_the_download_is_logged_and_a_cross_site_request_is_refused(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->fetch($owner, $farm)->assertOk();

        $log = AuditLog::where('event', 'exported')->latest('id')->firstOrFail();
        $this->assertSame($farm->id, (int) $log->farm_id);
        $this->assertStringContainsString('ZIP', $log->subject);

        $this->actingAs($owner)
            ->withHeader('Sec-Fetch-Site', 'cross-site')
            ->get(route('farms.data-export.download', $farm))
            ->assertForbidden();
    }

    public function test_the_export_page_is_for_the_owner_and_the_legal_pages_are_public(): void
    {
        [$owner, $farm] = $this->farmWith();

        $this->actingAs($owner)->get(route('farms.data-export.show', $farm))->assertOk()->assertSee('ZIP নামান');

        auth()->logout();

        $this->get('/privacy')->assertOk()->assertSee('গোপনীয়তা নীতি');
        $this->get('/terms')->assertOk()->assertSee('শর্তাবলী');
    }
}
