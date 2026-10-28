<?php

namespace App\Services;

use App\Models\Farm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * একটা খামারের সব ডেটা ZIP-এ: প্রতিটি টেবিল একটা CSV (UTF-8, Excel-এ বাংলা ঠিক দেখায়)।
 * বাতিল ও মোছা রেকর্ডও থাকে (পূর্ণ ইতিহাস)। বড় ডেটায় মেমোরি না ভরতে সারি ধরে ধরে লেখা হয়।
 */
class FarmDataExport
{
    public const TABLES = [
        'cows' => 'গরু',
        'weight_records' => 'ওজনের রেকর্ড',
        'milk_records' => 'দুধের রেকর্ড',
        'health_events' => 'চিকিৎসা ও টিকা',
        'breeding_events' => 'প্রজনন',
        'feed_types' => 'খাবারের ধরন',
        'feed_records' => 'খাবারের রেকর্ড',
        'customers' => 'গ্রাহক',
        'sales' => 'বিক্রি',
        'expenses' => 'খরচ',
        'payments' => 'পেমেন্ট (আদায় ও পরিশোধ)',
    ];

    /** ZIP ফাইলের পথ ফেরত দেয় (পাঠানোর পর মুছে ফেলার দায়িত্ব কলকারীর) */
    public function build(Farm $farm, bool $withPhotos = false): string
    {
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $zipPath = tempnam($dir, 'export');
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ZIP ফাইল তৈরি করা যায়নি।');
        }

        $temps = [];
        $counts = [];

        try {
            foreach (self::TABLES as $table => $label) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'farm_id')) {
                    continue;
                }

                $columns = Schema::getColumnListing($table);

                [$path, $rows] = $this->csv($dir, $columns, $this->tableRows($table, $farm->id, $columns));
                $temps[] = $path;
                $zip->addFile($path, "{$table}.csv");
                $counts["{$table}.csv"] = [$label, $rows];
            }

            // সদস্য: নাম, ইমেইল ও ভূমিকা
            $members = DB::table('farm_user')
                ->join('users', 'users.id', '=', 'farm_user.user_id')
                ->where('farm_user.farm_id', $farm->id)
                ->orderBy('farm_user.user_id')
                ->get(['users.name', 'users.email', 'farm_user.role', 'farm_user.created_at as joined_at']);

            [$path, $rows] = $this->csv(
                $dir,
                ['name', 'email', 'role', 'joined_at'],
                $members->map(fn($m) => [$m->name, $m->email, $m->role, $m->joined_at])->all()
            );
            $temps[] = $path;
            $zip->addFile($path, 'members.csv');
            $counts['members.csv'] = ['সদস্য', $rows];

            // অডিট লগ (ব্রাউজারের পরিচয় বাদে)
            if (Schema::hasTable('audit_logs')) {
                $columns = array_values(array_diff(Schema::getColumnListing('audit_logs'), ['user_agent']));

                [$path, $rows] = $this->csv($dir, $columns, $this->tableRows('audit_logs', $farm->id, $columns));
                $temps[] = $path;
                $zip->addFile($path, 'audit_logs.csv');
                $counts['audit_logs.csv'] = ['অডিট লগ', $rows];
            }

            $photos = 0;

            if ($withPhotos) {
                $disk = Storage::disk('public');

                foreach (DB::table('cows')->where('farm_id', $farm->id)->whereNotNull('photo_path')->get(['id', 'photo_path']) as $cow) {
                    if ($disk->exists($cow->photo_path)) {
                        $zip->addFile($disk->path($cow->photo_path), 'photos/cow-' . $cow->id . '-' . basename($cow->photo_path));
                        $photos++;
                    }
                }
            }

            $zip->addFromString('README.txt', $this->readme($farm, $counts, $withPhotos ? $photos : null));
            $zip->close();
        } catch (\Throwable $e) {
            @$zip->close();
            @unlink($zipPath);

            throw $e;
        } finally {
            foreach ($temps as $temp) {
                @unlink($temp);
            }
        }

        return $zipPath;
    }

    private function tableRows(string $table, int $farmId, array $columns): \Generator
    {
        foreach (DB::table($table)->where('farm_id', $farmId)->lazyById(1000) as $row) {
            yield array_map(fn($column) => $row->{$column} ?? null, $columns);
        }
    }

    /** @return array{0: string, 1: int} [ফাইলের পথ, সারির সংখ্যা] */
    private function csv(string $dir, array $columns, iterable $rows): array
    {
        $path = tempnam($dir, 'csv');
        $handle = fopen($path, 'w');

        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM, যাতে Excel-এ বাংলা ঠিক দেখায়
        fputcsv($handle, $columns, ',', '"', '\\');

        $count = 0;
        foreach ($rows as $row) {
            fputcsv($handle, array_map([$this, 'cell'], $row), ',', '"', '\\');
            $count++;
        }

        fclose($handle);

        return [$path, $count];
    }

    /** Excel-এ "=...", "+...", "@..." সূত্র হিসেবে চলে না যায়, তাই শুরুতে ' বসে (সংখ্যা ও ফোন নম্বর যেমন আছে) */
    private function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        $text = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);

        if ($text !== '' && ! is_numeric($text) && preg_match('/^[=+\-@\t\r]/', $text)) {
            return "'" . $text;
        }

        return $text;
    }

    private function readme(Farm $farm, array $counts, ?int $photos): string
    {
        $lines = [
            'খামারের ডেটা এক্সপোর্ট',
            'খামার: ' . $farm->name,
            'তৈরির সময়: ' . now()->format('d/m/Y H:i'),
            '',
            'প্রতিটি .csv ফাইল Excel বা LibreOffice-এ খোলা যায় (UTF-8)।',
            'বাতিল (status = void) ও মোছা (deleted_at ফাঁকা নয়) রেকর্ডসহ পুরো ইতিহাস এতে আছে।',
            'কলামের নাম ডাটাবেসের নামেই দেওয়া, যেমন total_amount = মোট টাকা, paid_at = পরিশোধের তারিখ।',
            '',
            'ফাইলের তালিকা:',
        ];

        foreach ($counts as $file => [$label, $rows]) {
            $lines[] = "- {$file}: {$label}, {$rows}টি সারি";
        }

        if ($photos !== null) {
            $lines[] = "- photos/: গরুর ছবি, {$photos}টি";
        }

        $lines[] = '';
        $lines[] = 'সতর্কতা: এই ফাইলে গ্রাহকের নাম-ফোন ও সদস্যদের ইমেইলের মতো ব্যক্তিগত তথ্য আছে। নিরাপদ জায়গায় রাখুন, অন্যের সঙ্গে ছড়াবেন না।';

        return implode("\n", $lines) . "\n";
    }
}
