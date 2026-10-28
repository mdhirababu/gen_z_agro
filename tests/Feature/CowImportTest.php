<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cow;
use App\Models\Farm;
use App\Models\Plan;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class CowImportTest extends TestCase
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

    /** ইংরেজি হেডারে CSV (প্রতিটি সারি ফিল্ড => মান); হেডার হয় সব সারির কী মিলিয়ে */
    private function csv(array $rows, ?array $headers = null): string
    {
        if ($headers === null) {
            $headers = [];

            foreach ($rows as $row) {
                foreach (array_keys($row) as $key) {
                    if (! in_array($key, $headers, true)) {
                        $headers[] = $key;
                    }
                }
            }

            $headers = $headers ?: ['tag_number'];
        }

        $handle = fopen('php://memory', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn($key) => $row[$key] ?? '', $headers), ',', '"', '\\');
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function xlsx(array $grid): string
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray($grid, null, 'A1', true);

        ob_start();
        (new XlsxWriter($book))->save('php://output');

        return (string) ob_get_clean();
    }

    private function upload(User $user, Farm $farm, string $content, string $name = 'cows.csv'): TestResponse
    {
        return $this->actingAs($user)->post(route('farms.cows.import.upload', $farm), [
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ]);
    }

    private function tokenFrom(TestResponse $response): string
    {
        return basename((string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
    }

    /** আপলোড করে টোকেন ফেরত দেয় */
    private function begin(User $user, Farm $farm, array $rows, ?array $headers = null): string
    {
        $response = $this->upload($user, $farm, $this->csv($rows, $headers));
        $response->assertSessionHasNoErrors();

        return $this->tokenFrom($response);
    }

    private function analysis(User $user, Farm $farm, string $token): array
    {
        return $this->actingAs($user)->get(route('farms.cows.import.preview', [$farm, $token]))->assertOk()->viewData('analysis');
    }

    private function confirm(User $user, Farm $farm, string $token): TestResponse
    {
        return $this->actingAs($user)->post(route('farms.cows.import.store', [$farm, $token]));
    }

    private function row(array $analysis, string $tag): array
    {
        return collect($analysis['rows'])->first(fn($r) => $r['tag'] === $tag);
    }

    public function test_the_template_has_the_headers_dropdowns_and_instructions(): void
    {
        [$owner, $farm] = $this->farmWith();

        $response = $this->actingAs($owner)->get(route('farms.cows.import.template', $farm))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));

        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $response->streamedContent());
        $book = (new XlsxReader())->load($path);
        @unlink($path);

        $headers = $book->getSheet(0)->rangeToArray('A1:P1')[0];
        $this->assertContains('ট্যাগ নম্বর *', $headers);
        $this->assertContains('ধরন *', $headers);
        $this->assertContains('লিঙ্গ *', $headers);
        $this->assertNotNull($book->getSheetByName('নির্দেশনা'));
    }

    public function test_a_csv_is_previewed_and_then_imported_with_bangla_words(): void
    {
        [$owner, $farm] = $this->farmWith();

        $token = $this->begin($owner, $farm, [
            [
                'tag_number' => 'A-1',
                'name' => 'লক্ষ্মী',
                'category' => 'দুধ দিচ্ছে',
                'sex' => 'মাদি',
                'breed' => 'হলস্টেইন ক্রস',
                'acquisition_type' => 'কেনা',
                'purchase_date' => '2024-01-10',
                'purchase_price' => '80,000'
            ],
            ['tag_number' => 'B-2', 'category' => 'bull', 'sex' => 'male', 'acquisition_type' => 'born', 'birth_date' => '2024-02-01'],
        ]);

        $analysis = $this->analysis($owner, $farm, $token);
        $this->assertSame(2, $analysis['counts']['ok']);
        $this->assertSame(0, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->count()); // প্রিভিউতে কিছু সংরক্ষণ হয় না

        $this->confirm($owner, $farm, $token)
            ->assertRedirect(route('farms.cows.index', $farm))
            ->assertSessionHas('success');

        $a = Cow::withoutGlobalScopes()->where('tag_number', 'A-1')->firstOrFail();
        $this->assertSame($farm->id, $a->farm_id);
        $this->assertSame($owner->id, $a->created_by);
        $this->assertSame('import', $a->source);
        $this->assertSame('milking', $a->category);
        $this->assertSame('female', $a->sex);
        $this->assertSame('holstein_cross', $a->breed);
        $this->assertSame('purchased', $a->acquisition_type);
        $this->assertEquals(80000, $a->purchase_price);

        $b = Cow::withoutGlobalScopes()->where('tag_number', 'B-2')->firstOrFail();
        $this->assertSame('bull', $b->category);
        $this->assertSame('2024-02-01', $b->birth_date->toDateString());
    }

    public function test_an_xlsx_with_excel_dates_numeric_tags_and_bangla_digits(): void
    {
        [$owner, $farm] = $this->farmWith();

        $content = $this->xlsx([
            ['ট্যাগ নম্বর *', 'ধরন *', 'লিঙ্গ *', 'জন্মতারিখ', 'ক্রয়মূল্য', 'কীভাবে এল'],
            [101, 'শুকনো', 'মাদি', 45000, '৫০,০০০', 'কেনা'], // 45000 = 2023-03-15
        ]);

        $response = $this->upload($owner, $farm, $content, 'cows.xlsx');
        $response->assertSessionHasNoErrors();
        $token = $this->tokenFrom($response);

        $this->confirm($owner, $farm, $token)->assertSessionHas('success');

        $cow = Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->firstOrFail();
        $this->assertSame('101', $cow->tag_number);
        $this->assertSame('dry', $cow->category);
        $this->assertSame('2023-03-15', $cow->birth_date->toDateString());
        $this->assertEquals(50000, $cow->purchase_price);
    }

    public function test_existing_tags_are_skipped_and_duplicates_in_the_file_are_errors(): void
    {
        [$owner, $farm] = $this->farmWith();
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'A-1']);

        $token = $this->begin($owner, $farm, [
            ['tag_number' => 'a-1', 'category' => 'milking', 'sex' => 'female'], // ছোট হাতের, তবু আগেই আছে
            ['tag_number' => 'C-3', 'category' => 'milking', 'sex' => 'female'],
            ['tag_number' => 'D-4', 'category' => 'milking', 'sex' => 'female'],
            ['tag_number' => 'd-4', 'category' => 'milking', 'sex' => 'female'], // ফাইলে দুবার
        ]);

        $analysis = $this->analysis($owner, $farm, $token);

        $this->assertSame('exists', $this->row($analysis, 'a-1')['state']);
        $this->assertSame('ok', $this->row($analysis, 'C-3')['state']);
        $this->assertSame('ok', $this->row($analysis, 'D-4')['state']);
        $this->assertSame('error', $this->row($analysis, 'd-4')['state']);

        $this->confirm($owner, $farm, $token)->assertSessionHas('success');

        $this->assertSame(1, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->whereIn('tag_number', ['A-1', 'a-1'])->count());
        $this->assertSame(2, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->where('source', 'import')->count());
    }

    public function test_the_same_rules_as_the_cow_form_apply(): void
    {
        [$owner, $farm] = $this->farmWith();

        $token = $this->begin($owner, $farm, [
            ['tag_number' => 'E1', 'category' => 'milking', 'sex' => 'male'],                                        // দুধ দিচ্ছে নর হতে পারে না
            ['tag_number' => 'E2', 'category' => 'bull', 'sex' => 'female'],                                         // ষাঁড় মাদি হতে পারে না
            ['tag_number' => 'E3', 'category' => 'calf', 'sex' => 'female', 'acquisition_type' => 'born'],           // জন্ম হলে জন্মতারিখ চাই
            ['tag_number' => 'E4', 'category' => 'calf', 'sex' => 'female', 'birth_date' => now()->addDay()->toDateString()],
            ['tag_number' => 'E5', 'category' => 'milking', 'sex' => 'female', 'status' => 'sold'],
            ['tag_number' => 'E6', 'category' => 'milking', 'sex' => 'female', 'status' => 'dead'],                   // বাদ পড়ার তারিখ চাই
            ['tag_number' => 'E7', 'category' => 'ঘোড়া', 'sex' => 'female'],                                        // ধরন চেনা যায় না
            ['tag_number' => 'E8', 'category' => 'milking', 'sex' => 'female', 'purchase_price' => 'অনেক'],
            ['tag_number' => 'E9', 'category' => 'milking', 'sex' => 'female', 'birth_date' => '31/02/2024'],         // অবৈধ তারিখ
            ['tag_number' => '', 'category' => 'milking', 'sex' => 'female'],
        ]);

        $analysis = $this->analysis($owner, $farm, $token);
        $errorsOf = fn(string $tag) => implode(' ', $this->row($analysis, $tag)['errors']);

        $this->assertStringContainsString('মাদি', $errorsOf('E1'));
        $this->assertStringContainsString('নর', $errorsOf('E2'));
        $this->assertStringContainsString('জন্মতারিখ', $errorsOf('E3'));
        $this->assertStringContainsString('ভবিষ্যতের', $errorsOf('E4'));
        $this->assertStringContainsString('বিক্রিত', $errorsOf('E5'));
        $this->assertStringContainsString('বাদ পড়ার তারিখ', $errorsOf('E6'));
        $this->assertStringContainsString('ধরন চেনা যায়নি', $errorsOf('E7'));
        $this->assertStringContainsString('সংখ্যা', $errorsOf('E8'));
        $this->assertStringContainsString('বোঝা যায়নি', $errorsOf('E9'));
        $this->assertSame(10, $analysis['counts']['error']);

        // সব ভুল: কিছুই যোগ হয় না
        $this->confirm($owner, $farm, $token);
        $this->assertSame(0, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->count());
    }

    public function test_mothers_can_be_in_the_file_or_already_on_the_farm(): void
    {
        [$owner, $farm] = $this->farmWith();

        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'DBM', 'sex' => 'female', 'category' => 'milking', 'birth_date' => '2019-01-01']);
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'BULL-1', 'sex' => 'male', 'category' => 'bull']);

        $token = $this->begin($owner, $farm, [
            // বাছুর আগে, মা পরে: ক্রম গুরুত্বপূর্ণ নয়
            ['tag_number' => 'K-1', 'category' => 'calf', 'sex' => 'female', 'birth_date' => '2024-01-01', 'mother_tag' => 'M-1'],
            ['tag_number' => 'M-1', 'category' => 'milking', 'sex' => 'female', 'birth_date' => '2020-01-01'],
            ['tag_number' => 'K-2', 'category' => 'calf', 'sex' => 'female', 'birth_date' => '2024-01-01', 'mother_tag' => 'dbm'],
            ['tag_number' => 'K-3', 'category' => 'calf', 'sex' => 'female', 'mother_tag' => 'NOPE'],
            ['tag_number' => 'K-4', 'category' => 'calf', 'sex' => 'female', 'mother_tag' => 'BULL-1'],
            ['tag_number' => 'K-5', 'category' => 'calf', 'sex' => 'female', 'birth_date' => '2015-01-01', 'mother_tag' => 'M-1'],
            ['tag_number' => 'X-1', 'category' => 'heifer', 'sex' => 'female', 'mother_tag' => 'X-2'],
            ['tag_number' => 'X-2', 'category' => 'heifer', 'sex' => 'female', 'mother_tag' => 'X-1'],
        ]);

        $analysis = $this->analysis($owner, $farm, $token);

        foreach (['K-1', 'M-1', 'K-2'] as $tag) {
            $this->assertSame('ok', $this->row($analysis, $tag)['state'], $tag);
        }
        foreach (['K-3', 'K-4', 'K-5', 'X-1', 'X-2'] as $tag) {
            $this->assertSame('error', $this->row($analysis, $tag)['state'], $tag);
        }

        $this->confirm($owner, $farm, $token)->assertSessionHas('success');

        $find = fn(string $tag) => Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->where('tag_number', $tag)->firstOrFail();

        $this->assertSame($find('M-1')->id, $find('K-1')->mother_id);
        $this->assertSame($find('DBM')->id, $find('K-2')->mother_id);
    }

    public function test_the_plan_limit_blocks_the_whole_import_and_inactive_cows_do_not_count(): void
    {
        [$owner, $farm] = $this->farmWith();

        Plan::where('slug', 'basic')->update(['max_cows' => 3]);
        Cow::factory()->count(2)->create(['farm_id' => $farm->id]);

        $token = $this->begin($owner, $farm, [
            ['tag_number' => 'N-1', 'category' => 'milking', 'sex' => 'female'],
            ['tag_number' => 'N-2', 'category' => 'milking', 'sex' => 'female'],
        ]);

        $analysis = $this->analysis($owner, $farm, $token);
        $this->assertTrue($analysis['over_capacity']);
        $this->assertSame(1, $analysis['slots']);

        $this->confirm($owner, $farm, $token)->assertSessionHasErrors('limit');
        $this->assertSame(2, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->count());

        // একটা সক্রিয় আর একটা মৃত: মৃত জায়গা নেয় না
        $token = $this->begin($owner, $farm, [
            ['tag_number' => 'N-3', 'category' => 'milking', 'sex' => 'female'],
            ['tag_number' => 'N-4', 'category' => 'milking', 'sex' => 'female', 'status' => 'dead', 'exit_date' => '2024-03-01'],
        ]);

        $this->confirm($owner, $farm, $token)->assertSessionHas('success');
        $this->assertSame(4, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->count());
    }

    public function test_money_and_dates_follow_the_acquisition_type(): void
    {
        [$owner, $farm] = $this->farmWith();

        $token = $this->begin($owner, $farm, [
            [
                'tag_number' => 'Q1',
                'category' => 'calf',
                'sex' => 'female',
                'acquisition_type' => 'born',
                'birth_date' => '2024-01-01',
                'purchase_price' => '5000',
                'opening_value' => '9000',
                'purchase_date' => '2024-01-02'
            ],      // জন্মে দাম ও কেনার তারিখ বাদ
            [
                'tag_number' => 'Q2',
                'category' => 'milking',
                'sex' => 'female',
                'acquisition_type' => 'purchased',
                'purchase_price' => '70000',
                'opening_value' => '9000',
                'purchase_date' => '2024-02-02'
            ],
            [
                'tag_number' => 'Q3',
                'category' => 'milking',
                'sex' => 'female',                                  // না দিলে "আগে থেকে ছিল"
                'opening_value' => '60000',
                'purchase_price' => '1',
                'purchase_date' => '2024-02-02'
            ],
            ['tag_number' => 'Q4', 'category' => 'milking', 'sex' => 'female', 'status' => 'transferred', 'exit_date' => '2024-03-03'],
        ]);

        $this->confirm($owner, $farm, $token)->assertSessionHas('success');

        $find = fn(string $tag) => Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->where('tag_number', $tag)->firstOrFail();

        $born = $find('Q1');
        $this->assertEquals(0, $born->purchase_price);
        $this->assertEquals(0, $born->opening_value);
        $this->assertNull($born->purchase_date);

        $bought = $find('Q2');
        $this->assertEquals(70000, $bought->purchase_price);
        $this->assertEquals(0, $bought->opening_value);
        $this->assertSame('2024-02-02', $bought->purchase_date->toDateString());

        $existing = $find('Q3');
        $this->assertSame('existing', $existing->acquisition_type);
        $this->assertEquals(60000, $existing->opening_value);
        $this->assertEquals(0, $existing->purchase_price);
        $this->assertNull($existing->purchase_date);

        $gone = $find('Q4');
        $this->assertSame('transferred', $gone->status);
        $this->assertSame('2024-03-03', $gone->exit_date->toDateString());
    }

    public function test_an_unknown_breed_becomes_other_and_is_kept_in_the_notes(): void
    {
        [$owner, $farm] = $this->farmWith();

        $token = $this->begin($owner, $farm, [
            ['tag_number' => 'B1', 'category' => 'milking', 'sex' => 'female', 'breed' => 'দেশী মিশ্র', 'notes' => 'শান্ত'],
        ]);

        $analysis = $this->analysis($owner, $farm, $token);
        $this->assertNotEmpty($this->row($analysis, 'B1')['warnings']);

        $this->confirm($owner, $farm, $token);

        $cow = Cow::withoutGlobalScopes()->where('tag_number', 'B1')->firstOrFail();
        $this->assertSame('other', $cow->breed);
        $this->assertStringContainsString('জাত: দেশী মিশ্র', $cow->notes);
        $this->assertStringContainsString('শান্ত', $cow->notes);
    }

    public function test_bad_files_are_rejected_with_a_clear_message(): void
    {
        [$owner, $farm] = $this->farmWith();

        // জরুরি কলাম নেই
        $this->upload($owner, $farm, $this->csv([['tag_number' => 'A', 'category' => 'milking']]))
            ->assertSessionHasErrors('file');

        // কোনো কলামই চেনা যায় না
        $this->upload($owner, $farm, $this->csv([['foo' => 'a', 'bar' => 'b']]))->assertSessionHasErrors('file');

        // শুধু হেডার
        $this->upload($owner, $farm, "tag_number,category,sex\n")->assertSessionHasErrors('file');

        // UTF-8 নয়
        $this->upload($owner, $farm, "tag_number,category,sex\n\xE0\xE1\xE2,milking,female\n")->assertSessionHasErrors('file');

        // ভুল ধরনের ফাইল
        $this->upload($owner, $farm, 'x', 'cows.pdf')->assertSessionHasErrors('file');

        // সারির সীমা
        config(['farm.import.max_rows' => 2]);
        $rows = array_map(fn($i) => ['tag_number' => "R{$i}", 'category' => 'milking', 'sex' => 'female'], [1, 2, 3]);
        $this->upload($owner, $farm, $this->csv($rows))->assertSessionHasErrors('file');

        $this->assertSame(0, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->count());
    }

    public function test_only_owner_and_manager_can_import_and_an_expired_farm_cannot_write(): void
    {
        [$staff, $farm] = $this->farmWith('staff');

        $this->actingAs($staff)->get(route('farms.cows.import', $farm))->assertForbidden();
        $this->actingAs($staff)->get(route('farms.cows.import.template', $farm))->assertForbidden();
        $this->upload($staff, $farm, $this->csv([['tag_number' => 'S1', 'category' => 'milking', 'sex' => 'female']]))->assertForbidden();

        [$manager, $farm2] = $this->farmWith('manager');
        $this->actingAs($manager)->get(route('farms.cows.import', $farm2))->assertOk();
        $this->begin($manager, $farm2, [['tag_number' => 'M1', 'category' => 'milking', 'sex' => 'female']]);

        [$otherOwner] = $this->farmWith();
        $this->assertContains($this->actingAs($otherOwner)->get(route('farms.cows.import', $farm2))->status(), [403, 404]);

        // মেয়াদ শেষ: পাতা দেখা যায়, আপলোড নয়
        [$owner, $expired] = $this->farmWith();
        $expired->subscription->update(['trial_ends_at' => now()->subDay()]);

        $this->actingAs($owner)->get(route('farms.cows.import', $expired))->assertOk();
        $this->upload($owner, $expired, $this->csv([['tag_number' => 'X', 'category' => 'milking', 'sex' => 'female']]))
            ->assertSessionHas('error');
    }

    public function test_confirming_checks_everything_again(): void
    {
        [$owner, $farm] = $this->farmWith();

        $token = $this->begin($owner, $farm, [
            ['tag_number' => 'Z-9', 'category' => 'milking', 'sex' => 'female'],
            ['tag_number' => 'Z-8', 'category' => 'milking', 'sex' => 'female'],
        ]);

        // প্রিভিউ আর নিশ্চিত করার মাঝে কেউ একই ট্যাগ যোগ করল
        Cow::factory()->create(['farm_id' => $farm->id, 'tag_number' => 'Z-9']);

        $this->confirm($owner, $farm, $token)->assertSessionHas('success');

        $this->assertSame(1, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->where('tag_number', 'Z-9')->count());
        $this->assertSame(1, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->where('tag_number', 'Z-8')->count());

        // একই টোকেন আবার ব্যবহার করা যায় না
        $this->confirm($owner, $farm, $token)->assertSessionHas('error');
        $this->assertSame(2, Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->count());
    }

    public function test_a_preview_belongs_to_one_user_and_one_farm(): void
    {
        [$owner, $farm] = $this->farmWith();
        [$otherOwner, $other] = $this->farmWith();

        $token = $this->begin($owner, $farm, [['tag_number' => 'P1', 'category' => 'milking', 'sex' => 'female']]);

        $this->actingAs($otherOwner)->get(route('farms.cows.import.preview', [$other, $token]))
            ->assertRedirect(route('farms.cows.import', $other));

        $this->confirm($otherOwner, $other, $token)->assertSessionHas('error');
        $this->assertSame(0, Cow::withoutGlobalScopes()->where('farm_id', $other->id)->count());

        // ভুল ধাঁচের টোকেন
        $this->actingAs($owner)->get(route('farms.cows.import', $farm) . '/short')->assertNotFound();
    }

    public function test_the_import_is_written_to_the_audit_log(): void
    {
        [$owner, $farm] = $this->farmWith();

        $token = $this->begin($owner, $farm, [
            ['tag_number' => 'L1', 'category' => 'milking', 'sex' => 'female'],
            ['tag_number' => 'L2', 'category' => 'milking', 'sex' => 'female'],
        ]);
        $this->confirm($owner, $farm, $token);

        $log = AuditLog::where('event', 'imported')->firstOrFail();

        $this->assertSame($farm->id, (int) $log->farm_id);
        $this->assertSame($owner->id, (int) $log->user_id);
        $this->assertEquals(2, $log->meta['created']);
        $this->assertSame(2, AuditLog::where('auditable_type', 'Cow')->where('event', 'created')->count());
    }

    public function test_a_filled_in_copy_of_the_real_template_can_be_imported(): void
    {
        [$owner, $farm] = $this->farmWith();

        $template = $this->actingAs($owner)->get(route('farms.cows.import.template', $farm))->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $template);
        $book = (new XlsxReader())->load($path);
        @unlink($path);

        // কলাম: A ট্যাগ, B নাম, C ধরন, D লিঙ্গ
        $sheet = $book->getSheet(0);
        $sheet->setCellValueExplicit('A2', '007', DataType::TYPE_STRING);
        $sheet->setCellValue('B2', 'রূপা');
        $sheet->setCellValue('C2', 'দুধ দিচ্ছে');
        $sheet->setCellValue('D2', 'মাদি');

        ob_start();
        (new XlsxWriter($book))->save('php://output');
        $filled = (string) ob_get_clean();

        $response = $this->upload($owner, $farm, $filled, 'cows.xlsx');
        $response->assertSessionHasNoErrors();

        $this->confirm($owner, $farm, $this->tokenFrom($response))->assertSessionHas('success');

        $cow = Cow::withoutGlobalScopes()->where('farm_id', $farm->id)->firstOrFail();

        $this->assertSame('007', $cow->tag_number); // শুরুর শূন্য হারায়নি
        $this->assertSame('রূপা', $cow->name);
        $this->assertSame('milking', $cow->category);
        $this->assertSame('female', $cow->sex);
        $this->assertSame('import', $cow->source);
    }
}
