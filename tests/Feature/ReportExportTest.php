<?php

namespace Tests\Feature;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\Plan;
use App\Models\User;
use App\Services\ReportPdf;
use App\Services\ReportService;
use App\Services\SubscriptionService;
use App\Support\ReportDocuments;
use App\Support\ReportPeriod;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private const Q = ['r' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PlanSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));
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

    private function cow(Farm $farm, string $tag, array $override = []): Cow
    {
        return Cow::factory()->create(array_merge([
            'farm_id' => $farm->id,
            'tag_number' => $tag,
            'acquisition_type' => 'purchased',
            'purchase_price' => 50000,
            'purchase_date' => '2026-09-01',
        ], $override));
    }

    private function sell(User $user, Farm $farm, array $override = []): void
    {
        $this->actingAs($user)->post(route('farms.sales.store', $farm), array_merge([
            'sale_type' => 'milk',
            'sold_at' => '2026-10-05',
            'quantity' => 20,
            'unit_price' => 60,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ], $override))->assertSessionHasNoErrors();
    }

    private function spend(User $user, Farm $farm, array $override = []): void
    {
        $this->actingAs($user)->post(route('farms.expenses.store', $farm), array_merge([
            'category' => 'labor',
            'expense_date' => '2026-10-03',
            'total_amount' => 500,
            'pay_mode' => 'paid',
            'pay_method' => 'cash',
        ], $override))->assertSessionHasNoErrors();
    }

    private function export(User $user, Farm $farm, string $report, string $format, array $extra = [])
    {
        return $this->actingAs($user)->get(route('farms.reports.export', [$farm, $report, $format] + self::Q + $extra));
    }

    /** xlsx-এর সব ঘরের মান একটা সমতল তালিকায় (সূত্র থাকলে হিসাব করা মান) */
    private function cells(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xl');
        file_put_contents($path, $binary);

        try {
            $sheet = (new XlsxReader())->load($path)->getActiveSheet();

            return collect($sheet->toArray(null, true, false))->flatten()->all();
        } finally {
            @unlink($path);
        }
    }

    private function fakePdf(bool $ready, string $content = '%PDF-FAKE'): ReportPdf
    {
        return new class($ready, $content) extends ReportPdf {
            public function __construct(private bool $ready, private string $content) {}

            public function fontsReady(): bool
            {
                return $this->ready;
            }

            public function build(array $doc): string
            {
                return $this->content;
            }
        };
    }

    public function test_every_report_downloads_as_an_excel_file(): void
    {
        [$owner, $farm] = $this->farmWith();

        foreach (['cash-flow', 'profit-loss', 'cows'] as $report) {
            $response = $this->export($owner, $farm, $report, 'xlsx')->assertOk();

            $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
            $this->assertStringContainsString("genz-agro-{$report}-2026-10-01_2026-10-10.xlsx", $response->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('PK', $response->streamedContent()); // xlsx আসলে একটা zip
        }
    }

    public function test_excel_has_a_title_and_real_numbers(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->sell($owner, $farm, ['quantity' => 20, 'unit_price' => 60]); // ১২০০ নগদ

        $cells = $this->cells($this->export($owner, $farm, 'cash-flow', 'xlsx')->streamedContent());

        $this->assertContains('নগদ প্রবাহ', $cells);
        $this->assertTrue(collect($cells)->contains(fn($v) => is_numeric($v) && abs($v - 1200) < 0.01));
    }

    public function test_user_text_never_turns_into_an_excel_formula(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->cow($farm, '=1+1', ['name' => null]);

        $cells = $this->cells($this->export($owner, $farm, 'cows', 'xlsx')->streamedContent());

        // সূত্র হলে হিসাব হয়ে 2 আসত, লেখা হলে হুবহু "=1+1" থাকে
        $this->assertContains('=1+1', $cells);
    }

    public function test_cow_export_follows_the_status_filter(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->cow($farm, 'LIVE-1', ['name' => null]);
        $this->cow($farm, 'SOLD-1', ['name' => null, 'status' => 'sold', 'exit_date' => '2026-10-05']);

        $cells = $this->cells($this->export($owner, $farm, 'cows', 'xlsx', ['status' => 'sold'])->streamedContent());

        $this->assertTrue(collect($cells)->contains(fn($v) => $v === 'SOLD-1'));
        $this->assertFalse(collect($cells)->contains(fn($v) => $v === 'LIVE-1'));
    }

    public function test_pdf_without_fonts_explains_what_to_do(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->app->instance(ReportPdf::class, $this->fakePdf(false));

        $this->export($owner, $farm, 'profit-loss', 'pdf')
            ->assertRedirect(route('farms.reports.index', $farm))
            ->assertSessionHas('error', fn($m) => str_contains($m, 'storage/fonts'));
    }

    public function test_pdf_download_response(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->app->instance(ReportPdf::class, $this->fakePdf(true));

        $response = $this->export($owner, $farm, 'profit-loss', 'pdf')->assertOk();

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-FAKE', $response->streamedContent());
    }

    public function test_pdf_view_escapes_user_text(): void
    {
        $doc = [
            'title' => 'পরীক্ষা',
            'farm' => '<script>alert(1)</script>',
            'period' => 'x',
            'widths' => [],
            'landscape' => false,
            'notes' => ['<b>নোট</b>'],
            'sections' => [[
                'heading' => 'h',
                'columns' => ['a', 'b'],
                'rows' => [['<img src=x onerror=alert(1)>', 1200]],
                'money' => [1],
            ]],
        ];

        $html = view('reports.pdf.document', ['doc' => $doc])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_real_pdf_is_built_when_the_font_files_are_installed(): void
    {
        $pdf = app(ReportPdf::class);

        if (! $pdf->fontsReady()) {
            $this->markTestSkipped('storage/fonts-এ Hind Siliguri ফন্ট নেই।');
        }

        $doc = [
            'title' => 'নগদ প্রবাহ',
            'farm' => 'পরীক্ষা খামার',
            'period' => '০১/১০/২০২৬ থেকে ১০/১০/২০২৬',
            'widths' => [],
            'landscape' => false,
            'notes' => [],
            'sections' => [['heading' => 'সারসংক্ষেপ', 'columns' => ['বিষয়', 'টাকা'], 'rows' => [['নগদ আয়', 1200.0]], 'money' => [1]]],
        ];

        $this->assertStringStartsWith('%PDF', $pdf->build($doc));
    }

    public function test_download_needs_the_plan_feature(): void
    {
        [$owner, $farm] = $this->farmWith();

        Plan::where('slug', 'basic')->update(['features' => ['export' => false, 'cow_profit_report' => true]]);

        $this->export($owner, $farm, 'cash-flow', 'xlsx')
            ->assertRedirect(route('farms.reports.index', $farm))
            ->assertSessionHas('error');

        Plan::where('slug', 'basic')->update(['features' => ['export' => true, 'cow_profit_report' => false]]);

        $this->export($owner, $farm, 'cows', 'xlsx')->assertRedirect(route('farms.reports.index', $farm))->assertSessionHas('error');
        $this->export($owner, $farm, 'cash-flow', 'xlsx')->assertOk();
    }

    public function test_staff_cannot_download_and_unknown_report_or_format_is_404(): void
    {
        [$staff, $farm] = $this->farmWith('staff');
        $this->export($staff, $farm, 'cash-flow', 'xlsx')->assertForbidden();

        [$owner, $farm2] = $this->farmWith();
        $base = route('farms.reports.index', $farm2);

        $this->actingAs($owner)->get($base . '/secrets/export/xlsx')->assertNotFound();
        $this->actingAs($owner)->get($base . '/cash-flow/export/csv')->assertNotFound();
    }

    public function test_another_farms_report_cannot_be_downloaded(): void
    {
        [$owner] = $this->farmWith();
        [, $other] = $this->farmWith();

        $status = $this->export($owner, $other, 'cash-flow', 'xlsx')->status();

        $this->assertContains($status, [403, 404]);
    }

    public function test_expired_farm_can_still_download(): void
    {
        [$owner, $farm] = $this->farmWith();
        $farm->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->export($owner, $farm, 'profit-loss', 'xlsx')->assertOk();
    }

    public function test_documents_carry_the_same_totals_as_the_reports(): void
    {
        [$owner, $farm] = $this->farmWith();
        $this->sell($owner, $farm); // ১২০০
        $this->spend($owner, $farm); // ৫০০ নগদ

        app()->forgetInstance('currentFarm');
        $reports = app(ReportService::class);
        $period = ReportPeriod::make('custom', '2026-10-01', '2026-10-10');

        $cash = $reports->cashFlow($farm, $period);
        $doc = ReportDocuments::cashFlow($farm, $period, $cash);
        $summary = $doc['sections'][0]['rows'];

        $this->assertEqualsWithDelta(1200, $summary[0][1], 0.01);
        $this->assertEqualsWithDelta(500, $summary[1][1], 0.01);
        $this->assertEqualsWithDelta($cash['net'], $summary[2][1], 0.01);

        $pl = $reports->profitLoss($farm, $period);
        $plDoc = ReportDocuments::profitLoss($farm, $period, $pl);
        $last = collect($plDoc['sections'][0]['rows'])->last();

        $this->assertSame('আনুমানিক নিট ফল', $last[0]);
        $this->assertEqualsWithDelta($pl['net'], $last[1], 0.01);
    }
}
