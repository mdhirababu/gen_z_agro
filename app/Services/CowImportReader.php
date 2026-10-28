<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class CowImportReader
{
    /**
     * @return array{headers: array, rows: array, error: ?string}
     *         rows: [[ফাইলের সারি নম্বর, [ঘরের মান...]], ...]
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        $max = (int) config('farm.import.max_rows', 500);

        try {
            if ($extension === 'csv') {
                // ANSI (Windows) CSV-তে বাংলা ভেঙে যায়, তাই UTF-8 না হলে আগেই থামানো হয়
                if (! mb_check_encoding((string) file_get_contents($path), 'UTF-8')) {
                    return $this->fail('CSV ফাইলটা UTF-8 নয়, বাংলা ভেঙে যাবে। Excel-এ "CSV UTF-8" হিসেবে সংরক্ষণ করুন, অথবা সরাসরি .xlsx দিন।');
                }

                $reader = new Csv();
                $reader->setInputEncoding('UTF-8');
            } else {
                $reader = new Xlsx();
            }

            $reader->setReadDataOnly(true);
            // হেডার + সর্বোচ্চ সারি + আরেকটা (সীমা পার হয়েছে কি না বোঝার জন্য)
            $reader->setReadFilter(new CowImportReadFilter($max + 2));

            $sheet = $reader->load($path)->getSheet(0);
            $grid = $sheet->toArray(null, false, false, false); // সূত্র চালানো নয়, ফরম্যাট বদল নয়
        } catch (\Throwable) {
            return $this->fail('ফাইলটা পড়া যায়নি। টেমপ্লেটের .xlsx বা UTF-8 .csv ফাইল দিন।');
        }

        $headers = array_map(fn($cell) => is_scalar($cell) ? trim((string) $cell) : '', array_shift($grid) ?? []);

        $rows = [];
        foreach ($grid as $index => $cells) {
            if (! collect($cells)->contains(fn($cell) => $cell !== null && trim((string) $cell) !== '')) {
                continue; // পুরো ফাঁকা সারি
            }

            $rows[] = [$index + 2, $cells]; // হেডার ১ম সারি, তাই ডেটা ২ থেকে
        }

        if (count($rows) > $max) {
            return $this->fail("একবারে সর্বোচ্চ {$max}টি সারি দেওয়া যায়। ফাইল ভাগ করে আলাদা আলাদা দিন।");
        }

        return ['headers' => $headers, 'rows' => $rows, 'error' => null];
    }

    private function fail(string $message): array
    {
        return ['headers' => [], 'rows' => [], 'error' => $message];
    }
}
