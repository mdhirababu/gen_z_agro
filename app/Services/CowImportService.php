<?php

namespace App\Services;

use App\Models\Cow;
use App\Models\Farm;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CowImportService
{
    /** ফিল্ড => গ্রহণযোগ্য কলামের নাম (প্রথমটা টেমপ্লেটে দেখায়) */
    public const FIELDS = [
        'tag_number' => ['ট্যাগ নম্বর', 'ট্যাগ', 'tag_number', 'tag'],
        'name' => ['নাম', 'name'],
        'category' => ['ধরন', 'category'],
        'sex' => ['লিঙ্গ', 'sex'],
        'breed' => ['জাত', 'breed'],
        'birth_date' => ['জন্মতারিখ', 'জন্ম তারিখ', 'birth_date'],
        'birth_date_estimated' => ['জন্মতারিখ আনুমানিক', 'আনুমানিক জন্মতারিখ', 'birth_date_estimated'],
        'acquisition_type' => ['কীভাবে এল', 'কীভাবে এসেছে', 'acquisition_type'],
        'purchase_date' => ['কেনার তারিখ', 'purchase_date'],
        'purchase_price' => ['ক্রয়মূল্য', 'purchase_price'],
        'opening_value' => ['প্রারম্ভিক মূল্য', 'opening_value'],
        'mother_tag' => ['মায়ের ট্যাগ', 'mother_tag'],
        'sire_name' => ['পিতার নাম', 'ষাঁড়ের নাম', 'sire_name'],
        'status' => ['অবস্থা', 'status'],
        'exit_date' => ['বাদ পড়ার তারিখ', 'exit_date'],
        'notes' => ['নোট', 'notes'],
    ];

    public const REQUIRED = ['tag_number', 'category', 'sex'];

    // ---------------------------------------------------------------- ১. হেডার চেনা

    /**
     * @param  array  $headers  ফাইলের প্রথম সারি
     * @param  array  $rows     [[সারি নম্বর, [ঘরের মান...]], ...]
     * @return array{error: ?string, items: array}
     */
    public function parse(array $headers, array $rows): array
    {
        $aliases = [];
        foreach (self::FIELDS as $field => $names) {
            foreach ($names as $name) {
                $aliases[$this->norm($name)] = $field;
            }
        }

        $map = [];
        foreach ($headers as $index => $header) {
            $field = $aliases[$this->norm((string) $header)] ?? null;

            if ($field) {
                $map[$index] = $field;
            }
        }

        $missing = array_values(array_filter(self::REQUIRED, fn($field) => ! in_array($field, $map, true)));

        if ($missing) {
            $names = implode(', ', array_map(fn($field) => self::FIELDS[$field][0], $missing));

            return ['error' => "এই কলাম পাওয়া যায়নি: {$names}। নির্ধারিত টেমপ্লেট ব্যবহার করুন।", 'items' => []];
        }

        $items = [];
        foreach ($rows as [$number, $cells]) {
            $raw = [];

            foreach ($map as $index => $field) {
                $raw[$field] = $cells[$index] ?? null;
            }

            $items[] = ['row' => $number, 'raw' => $raw];
        }

        return ['error' => null, 'items' => $items];
    }

    // ---------------------------------------------------------------- ২. যাচাই

    /**
     * প্রতিটি সারির অবস্থা: ok (যোগ হবে), exists (আগেই আছে, বাদ), error (ভুল, বাদ)।
     * নিশ্চিত করার সময়ও এটা আবার চলে, তাই ক্যাশের পুরোনো ফল বিশ্বাস করা হয় না।
     */
    public function analyze(Farm $farm, array $items): array
    {
        $existing = Cow::withTrashed()->where('farm_id', $farm->id)->pluck('tag_number')
            ->mapWithKeys(fn($tag) => [$this->key($tag) => true])->all();

        $dbCows = $farm->cows()->get(['id', 'tag_number', 'sex', 'birth_date'])
            ->keyBy(fn($cow) => $this->key($cow->tag_number));

        $rows = [];
        $seen = [];

        foreach ($items as $item) {
            $row = $this->parseRow((int) $item['row'], (array) $item['raw']);

            if ($row['key'] !== null) {
                if (isset($existing[$row['key']])) {
                    $row['state'] = 'exists';
                    $row['errors'] = [];
                } elseif (isset($seen[$row['key']])) {
                    $row['errors'][] = "এই ট্যাগ ফাইলে আগেই এসেছে (সারি {$seen[$row['key']]})।";
                } else {
                    $seen[$row['key']] = $row['row'];
                }
            }

            if ($row['state'] === 'ok' && $row['errors']) {
                $row['state'] = 'error';
            }

            $rows[] = $row;
        }

        // এই ফাইলের ঠিক সারিগুলো (মা এদের মধ্যেও থাকতে পারে)
        $fileIndex = [];
        foreach ($rows as $i => $row) {
            if ($row['state'] === 'ok') {
                $fileIndex[$row['key']] = $i;
            }
        }

        // মা চেনা
        foreach ($rows as $i => $row) {
            if ($row['state'] !== 'ok' || $row['mother_tag'] === null) {
                continue;
            }

            $motherKey = $this->key($row['mother_tag']);
            $calfBirth = $row['data']['birth_date'];

            if ($motherKey === $row['key']) {
                $this->fail($rows[$i], 'নিজের মা নিজে হতে পারে না।');
                continue;
            }

            if (isset($dbCows[$motherKey])) {
                $mother = $dbCows[$motherKey];

                if ($mother->sex !== 'female') {
                    $this->fail($rows[$i], 'মা অবশ্যই মাদি গরু হতে হবে।');
                } elseif (! $this->motherBornBefore($mother->birth_date, $calfBirth)) {
                    $this->fail($rows[$i], 'মায়ের জন্মতারিখ বাছুরের জন্মতারিখের আগে হতে হবে।');
                } else {
                    $rows[$i]['mother_db_id'] = $mother->id;
                }

                continue;
            }

            if (! isset($fileIndex[$motherKey])) {
                $this->fail($rows[$i], "মায়ের ট্যাগ '{$row['mother_tag']}' খামারে বা এই ফাইলে পাওয়া যায়নি।");
                continue;
            }

            $motherRow = $rows[$fileIndex[$motherKey]];

            if ($motherRow['data']['sex'] !== 'female') {
                $this->fail($rows[$i], 'মা অবশ্যই মাদি গরু হতে হবে।');
            } elseif (! $this->motherBornBefore($motherRow['data']['birth_date'], $calfBirth)) {
                $this->fail($rows[$i], 'মায়ের জন্মতারিখ বাছুরের জন্মতারিখের আগে হতে হবে।');
            } else {
                $rows[$i]['mother_file_key'] = $motherKey;
            }
        }

        // মায়ের সারি ভুল হয়ে গেলে বা সম্পর্ক ঘুরে এলে সন্তানও বাদ (স্থির না হওয়া পর্যন্ত)
        do {
            $changed = false;

            foreach ($rows as $i => $row) {
                if ($row['state'] !== 'ok' || ! $row['mother_file_key']) {
                    continue;
                }

                if ($rows[$fileIndex[$row['mother_file_key']]]['state'] !== 'ok') {
                    $this->fail($rows[$i], 'মায়ের সারিতে ভুল আছে, আগে সেটা ঠিক করুন।');
                    $changed = true;
                } elseif ($this->cycles($rows, $fileIndex, $i)) {
                    $this->fail($rows[$i], 'মা-বাছুরের সম্পর্ক ঘুরে এসেছে।');
                    $changed = true;
                }
            }
        } while ($changed);

        // সারাংশ ও প্ল্যানের সীমা
        $counts = ['total' => count($rows), 'ok' => 0, 'exists' => 0, 'error' => 0];
        $activeNew = 0;
        $existingZero = 0;

        foreach ($rows as $row) {
            $counts[$row['state']]++;

            if ($row['state'] === 'ok') {
                $activeNew += $row['data']['status'] === 'active' ? 1 : 0;
                $existingZero += ($row['data']['acquisition_type'] === 'existing' && (float) $row['data']['opening_value'] == 0.0) ? 1 : 0;
            }
        }

        $slots = $farm->cowSlotsLeft();

        return [
            'rows' => $rows,
            'counts' => $counts,
            'active_new' => $activeNew,
            'slots' => $slots,
            'over_capacity' => $slots !== null && $activeNew > $slots,
            'existing_zero' => $existingZero,
        ];
    }

    // ---------------------------------------------------------------- ৩. ইমপোর্ট

    /** @return array{total: int, ok: int, exists: int, error: int, created: int} */
    public function import(Farm $farm, User $user, array $items): array
    {
        return DB::transaction(function () use ($farm, $user, $items) {
            // একসঙ্গে দুজন যোগ করলেও সীমা পার না হয়: খামারের সারি লক করে গোনা
            $locked = Farm::whereKey($farm->id)->lockForUpdate()->firstOrFail();
            $analysis = $this->analyze($locked, $items);

            if ($analysis['over_capacity']) {
                throw ValidationException::withMessages([
                    'limit' => 'আপনার প্ল্যানের গরুর সীমা শেষ। প্ল্যান আপগ্রেড করুন অথবা কম গরু দিন।',
                ]);
            }

            $ids = [];

            foreach ($analysis['rows'] as $row) {
                if ($row['state'] !== 'ok') {
                    continue;
                }

                $cow = new Cow($row['data']);
                $cow->forceFill([
                    'farm_id' => $locked->id,
                    'created_by' => $user->id,
                    'source' => 'import',
                    'mother_id' => $row['mother_db_id'],
                ])->save();

                $ids[$row['key']] = $cow->id;
            }

            // মা এই ফাইলেরই হলে সবাই তৈরি হওয়ার পর সম্পর্ক জোড়া
            foreach ($analysis['rows'] as $row) {
                if (
                    $row['state'] === 'ok' && $row['mother_file_key']
                    && isset($ids[$row['key']], $ids[$row['mother_file_key']])
                ) {
                    DB::table('cows')->where('id', $ids[$row['key']])->update(['mother_id' => $ids[$row['mother_file_key']]]);
                }
            }

            return $analysis['counts'] + ['created' => count($ids)];
        });
    }

    // ---------------------------------------------------------------- পাতায় দেখানোর জন্য

    /** @return array<string, array<int, string>> */
    public function allowed(): array
    {
        $labels = fn(array $options) => array_values(array_map(fn($names) => $names[0], $options));

        return [
            'ধরন' => $labels($this->categoryOptions()),
            'লিঙ্গ' => $labels($this->sexOptions()),
            'জাত' => $labels($this->breedOptions()),
            'কীভাবে এল' => $labels($this->acquisitionOptions()),
            'অবস্থা' => ['সক্রিয়', 'মৃত', 'সরানো হয়েছে'],
        ];
    }

    // ---------------------------------------------------------------- একটা সারির যাচাই

    private function parseRow(int $number, array $raw): array
    {
        $row = [
            'row' => $number,
            'tag' => null,
            'key' => null,
            'name' => null,
            'category' => null,
            'sex' => null,
            'state' => 'ok',
            'errors' => [],
            'warnings' => [],
            'data' => null,
            'mother_tag' => null,
            'mother_db_id' => null,
            'mother_file_key' => null,
        ];

        $err = function (string $message) use (&$row) {
            $row['errors'][] = $message;
        };

        $today = Carbon::today();

        // ট্যাগ
        $tag = $this->str($raw['tag_number'] ?? null);
        if ($tag === null) {
            $err('ট্যাগ নম্বর দিন।');
        } elseif (mb_strlen($tag) > 50) {
            $err('ট্যাগ নম্বর ৫০ অক্ষরের বেশি হতে পারবে না।');
        }
        $row['tag'] = $tag;
        $row['key'] = $tag !== null ? $this->key($tag) : null;

        // নাম, পিতার নাম, নোট
        $name = $this->str($raw['name'] ?? null);
        $sire = $this->str($raw['sire_name'] ?? null);
        $notes = $this->str($raw['notes'] ?? null);

        if ($name !== null && mb_strlen($name) > 100) {
            $err('নাম ১০০ অক্ষরের বেশি হতে পারবে না।');
        }
        if ($sire !== null && mb_strlen($sire) > 100) {
            $err('পিতার নাম ১০০ অক্ষরের বেশি হতে পারবে না।');
        }
        if ($notes !== null && mb_strlen($notes) > 2000) {
            $err('নোট ২০০০ অক্ষরের বেশি হতে পারবে না।');
        }
        $row['name'] = $name;

        // ধরন ও লিঙ্গ
        $categoryText = $this->str($raw['category'] ?? null);
        $category = $this->pick($categoryText, $this->categoryOptions());
        if ($categoryText === null) {
            $err('ধরন দিন।');
        } elseif ($category === null) {
            $err("ধরন চেনা যায়নি ('{$categoryText}')। এগুলোর একটা দিন: " . $this->list($this->categoryOptions()) . '।');
        }

        $sexText = $this->str($raw['sex'] ?? null);
        $sex = $this->pick($sexText, $this->sexOptions());
        if ($sexText === null) {
            $err('লিঙ্গ দিন।');
        } elseif ($sex === null) {
            $err("লিঙ্গ চেনা যায়নি ('{$sexText}')। মাদি বা নর লিখুন।");
        }

        if ($category && $sex) {
            if (in_array($category, ['milking', 'dry', 'heifer'], true) && $sex !== 'female') {
                $err('এই ধরনের গরু মাদি হতে হবে।');
            }
            if ($category === 'bull' && $sex !== 'male') {
                $err('ষাঁড় নর হতে হবে।');
            }
        }
        $row['category'] = $category;
        $row['sex'] = $sex;

        // জাত: চেনা না গেলে "অন্যান্য" ধরা হয়, আসল নামটা নোটে থাকে
        $breedText = $this->str($raw['breed'] ?? null);
        $breed = null;
        if ($breedText !== null) {
            $breed = $this->pick($breedText, $this->breedOptions());

            if ($breed === null) {
                $breed = 'other';
                $notes = trim("জাত: {$breedText}" . ($notes ? "\n{$notes}" : ''));
                $row['warnings'][] = "জাত '{$breedText}' চেনা যায়নি, 'অন্যান্য' ধরা হবে (নোটে লেখা থাকবে)।";
            }
        }

        // কীভাবে এল (না দিলে "আগে থেকে ছিল")
        $acquisitionText = $this->str($raw['acquisition_type'] ?? null);
        $acquisition = $acquisitionText === null ? 'existing' : $this->pick($acquisitionText, $this->acquisitionOptions());
        if ($acquisition === null) {
            $err("'কীভাবে এল' চেনা যায়নি ('{$acquisitionText}')। এগুলোর একটা দিন: " . $this->list($this->acquisitionOptions()) . '।');
        }

        // তারিখ ও টাকা
        $birth = $this->tryDate($raw['birth_date'] ?? null, 'জন্মতারিখ', $err);
        $purchaseDate = $this->tryDate($raw['purchase_date'] ?? null, 'কেনার তারিখ', $err);
        $exitDate = $this->tryDate($raw['exit_date'] ?? null, 'বাদ পড়ার তারিখ', $err);

        foreach ([['জন্মতারিখ', $birth], ['কেনার তারিখ', $purchaseDate], ['বাদ পড়ার তারিখ', $exitDate]] as [$label, $date]) {
            if ($date && $date->gt($today)) {
                $err("{$label} ভবিষ্যতের হতে পারবে না।");
            }
        }

        $price = $this->tryMoney($raw['purchase_price'] ?? null, 'ক্রয়মূল্য', $err);
        $opening = $this->tryMoney($raw['opening_value'] ?? null, 'প্রারম্ভিক মূল্য', $err);

        if ($acquisition === 'born' && ! $birth) {
            $err('খামারে জন্ম হলে জন্মতারিখ দিতে হবে।');
        }

        // অবস্থা (বিক্রিত নয়: বিক্রি আলাদা লেনদেন)
        $statusText = $this->str($raw['status'] ?? null);
        $status = 'active';
        if ($statusText !== null) {
            $picked = $this->pick($statusText, $this->statusOptions());

            if ($picked === 'sold') {
                $err('বিক্রিত গরু ইমপোর্ট করা যায় না। গরু রেখে ইমপোর্ট করুন, বিক্রি আলাদা লেনদেন হিসেবে যোগ করুন।');
            } elseif ($picked === null) {
                $err("অবস্থা চেনা যায়নি ('{$statusText}')। সক্রিয়, মৃত বা সরানো হয়েছে লিখুন।");
            } else {
                $status = $picked;
            }
        }

        if ($status !== 'active' && ! $exitDate) {
            $err('মৃত বা সরানো গরুর জন্য বাদ পড়ার তারিখ দিন।');
        }

        // মায়ের ট্যাগ (চেনা হয় analyze-এ)
        $row['mother_tag'] = $this->str($raw['mother_tag'] ?? null);

        // কেনা বা আগে থেকে ছিল: হিসাবের জন্য দরকারি তথ্য না থাকলে সতর্কতা
        if ($acquisition === 'purchased' && ! $purchaseDate) {
            $row['warnings'][] = 'কেনার তারিখ না দিলে নগদ প্রবাহে এই গরুর ক্রয়ের টাকা ধরা হবে না।';
        }

        // ফর্মের (SaveCowRequest::payload) মতোই একই স্বাভাবিকীকরণ
        $data = [
            'tag_number' => $tag,
            'name' => $name,
            'category' => $category,
            'breed' => $breed,
            'sex' => $sex,
            'birth_date' => $birth?->toDateString(),
            'birth_date_estimated' => $birth ? ($this->bool($raw['birth_date_estimated'] ?? null) ?? false) : false,
            'sire_name' => $sire,
            'acquisition_type' => $acquisition,
            'purchase_date' => $purchaseDate?->toDateString(),
            'purchase_price' => $price ?? 0,
            'opening_value' => $opening ?? 0,
            'status' => $status,
            'exit_date' => $status === 'active' ? null : $exitDate?->toDateString(),
            'notes' => $notes,
        ];

        switch ($acquisition) {
            case 'born':
                $data['purchase_date'] = null;
                $data['purchase_price'] = 0;
                $data['opening_value'] = 0;
                break;
            case 'purchased':
                $data['opening_value'] = 0;
                break;
            default: // existing
                $data['purchase_date'] = null;
                $data['purchase_price'] = 0;
        }

        $row['data'] = $data;

        return $row;
    }

    // ---------------------------------------------------------------- ছোট সহায়ক

    private function fail(array &$row, string $message): void
    {
        $row['errors'][] = $message;
        $row['state'] = 'error';
    }

    private function motherBornBefore(mixed $motherBirth, ?string $calfBirth): bool
    {
        if (! $motherBirth || ! $calfBirth) {
            return true;
        }

        return Carbon::parse($motherBirth)->lt(Carbon::parse($calfBirth));
    }

    private function cycles(array $rows, array $fileIndex, int $start): bool
    {
        $key = $rows[$start]['key'];
        $current = $rows[$start]['mother_file_key'];

        for ($step = 0; $current !== null && $step <= count($rows); $step++) {
            if ($current === $key) {
                return true;
            }

            $index = $fileIndex[$current] ?? null;

            if ($index === null || $rows[$index]['state'] !== 'ok') {
                return false;
            }

            $current = $rows[$index]['mother_file_key'];
        }

        return false;
    }

    /** ট্যাগ তুলনার চাবি: ছোট-বড় হাতের অক্ষর একই (MySQL-এর unique নিয়মের মতো) */
    private function key(?string $tag): string
    {
        return mb_strtolower(trim((string) $tag));
    }

    private function norm(?string $text): string
    {
        // আগে তারকা (টেমপ্লেটে জরুরি কলামের পাশে থাকে) ও অদৃশ্য অক্ষর সরানো, তারপর ছাঁটা:
        // উল্টো করলে "ট্যাগ নম্বর *" থেকে শেষে একটা ফাঁকা থেকে যায়
        $text = str_replace(['*', "\u{200C}", "\u{200D}"], '', (string) $text);

        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        // অদৃশ্য non-breaking space (Excel থেকে কপি করলে আসে) সহ সব ফাঁকা একটা ফাঁকায়
        $text = (string) preg_replace('/[\s_\x{00A0}]+/u', ' ', $text);

        return trim(mb_strtolower($text));
    }

    private function digits(string $text): string
    {
        return strtr($text, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    private function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) || is_int($value)) {
            $value = (is_float($value) && floor($value) == $value) ? (string) (int) $value : (string) $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function bool(mixed $value): ?bool
    {
        $text = $this->norm($this->str($value));

        if ($text === '') {
            return null;
        }

        return match (true) {
            in_array($text, ['হ্যাঁ', 'হ্যা', 'হাঁ', 'yes', 'y', 'true', '1'], true) => true,
            in_array($text, ['না', 'no', 'n', 'false', '0'], true) => false,
            default => null,
        };
    }

    /** @throws \InvalidArgumentException */
    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Excel-এর তারিখ আসলে একটা সংখ্যা (দিনের ক্রমিক)
        if (is_int($value) || is_float($value)) {
            if ($value > 20000 && $value < 80000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->startOfDay();
            }

            throw new \InvalidArgumentException('date');
        }

        $text = $this->digits(trim((string) $value));

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y', 'j.n.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $text);

                // ৩১/০২ ধাঁচের অবৈধ তারিখ যেন পরের মাসে গড়িয়ে না যায়
                if ($date && $date->format($format) === $text) {
                    return $date;
                }
            } catch (\Throwable) {
            }
        }

        throw new \InvalidArgumentException('date');
    }

    private function tryDate(mixed $value, string $label, callable $err): ?Carbon
    {
        try {
            return $this->date($value);
        } catch (\InvalidArgumentException) {
            $err("{$label} বোঝা যায়নি। 2024-05-10 বা 10/05/2024 ধাঁচে লিখুন।");

            return null;
        }
    }

    private function tryMoney(mixed $value, string $label, callable $err): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
        } else {
            $text = $this->digits(trim((string) $value));
            $text = str_replace([',', '৳', 'টাকা', '/-', ' '], '', $text);

            if ($text === '') {
                return null;
            }

            if (! is_numeric($text)) {
                $err("{$label} একটা সংখ্যা হতে হবে।");

                return null;
            }

            $number = (float) $text;
        }

        if ($number < 0 || $number > 99999999) {
            $err("{$label} ০ থেকে ৯,৯৯,৯৯,৯৯৯-এর মধ্যে হতে হবে।");

            return null;
        }

        return round($number, 2);
    }

    /** @param  array<string, array<int, string>>  $options  চাবি => [দেখানোর নাম, অন্য গ্রহণযোগ্য নাম...] */
    private function pick(?string $value, array $options): ?string
    {
        if ($value === null) {
            return null;
        }

        $needle = $this->norm($value);

        foreach ($options as $key => $names) {
            foreach ($names as $name) {
                if ($this->norm($name) === $needle) {
                    return $key;
                }
            }
        }

        return null;
    }

    private function list(array $options): string
    {
        return implode(', ', array_map(fn($names) => $names[0], $options));
    }

    private function categoryOptions(): array
    {
        $extra = [
            'milking' => ['গাভি', 'গাভী', 'দুগ্ধবতী', 'দুধেল'],
            'dry' => ['শুকনা'],
            'bull' => ['ষাড়'],
            'fattening' => ['মোটাতাজাকরণ'],
        ];

        $options = [];
        foreach (config('farm.categories') as $key => $meta) {
            $options[$key] = array_merge([$meta['label'], $key], $extra[$key] ?? []);
        }

        return $options;
    }

    private function sexOptions(): array
    {
        $extra = ['female' => ['f', 'স্ত্রী'], 'male' => ['m', 'পুরুষ']];

        $options = [];
        foreach (config('farm.sexes') as $key => $label) {
            $options[$key] = array_merge([$label, $key], $extra[$key] ?? []);
        }

        return $options;
    }

    private function breedOptions(): array
    {
        $options = [];
        foreach (config('farm.breeds') as $key => $label) {
            $options[$key] = [$label, $key];
        }

        return $options;
    }

    private function acquisitionOptions(): array
    {
        $extra = [
            'born' => ['জন্ম', 'নিজের', 'খামারে জন্ম'],
            'purchased' => ['কেনা', 'ক্রয়', 'কিনেছি'],
            'existing' => ['আগে থেকে ছিল', 'আগে থেকে', 'আগের'],
        ];

        $options = [];
        foreach (config('farm.acquisitions') as $key => $label) {
            $options[$key] = array_merge([$label, $key], $extra[$key] ?? []);
        }

        return $options;
    }

    private function statusOptions(): array
    {
        return [
            'active' => ['সক্রিয়', 'active', 'আছে'],
            'dead' => ['মৃত', 'dead', 'মারা গেছে'],
            'transferred' => ['সরানো হয়েছে', 'সরানো', 'transferred'],
            'sold' => ['বিক্রিত', 'sold', 'বিক্রি'],
        ];
    }
}
