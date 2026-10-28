<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CowImportTemplate
{
    public function build(): string
    {
        $allowed = app(CowImportService::class)->allowed();
        $max = (int) config('farm.import.max_rows', 500) + 1;

        $book = new Spreadsheet();
        $book->getProperties()->setCreator('Gen Z Agro')->setTitle('গরুর তালিকা টেমপ্লেট');

        $sheet = $book->getActiveSheet();
        $sheet->setTitle('গরু');

        $letters = [];
        $column = 1;

        foreach (CowImportService::FIELDS as $field => $names) {
            $letter = Coordinate::stringFromColumnIndex($column++);
            $letters[$field] = $letter;

            $label = $names[0] . (in_array($field, CowImportService::REQUIRED, true) ? ' *' : '');
            $sheet->setCellValueExplicit($letter . '1', $label, DataType::TYPE_STRING);
            $sheet->getColumnDimension($letter)->setWidth(max(16, mb_strlen($label) + 6));
        }

        $last = Coordinate::stringFromColumnIndex($column - 1);
        $header = $sheet->getStyle("A1:{$last}1");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E6F4EA');
        $sheet->freezePane('A2');

        // ট্যাগে শুরুর শূন্য (007) যেন হারিয়ে না যায়
        foreach (['tag_number', 'mother_tag'] as $field) {
            $sheet->getStyle("{$letters[$field]}2:{$letters[$field]}{$max}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        foreach (['birth_date', 'purchase_date', 'exit_date'] as $field) {
            $sheet->getStyle("{$letters[$field]}2:{$letters[$field]}{$max}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        }

        // ড্রপডাউন তালিকা (অন্য কিছু লিখলে সতর্কতা দেখায়, আটকায় না, কারণ অন্য প্রচলিত নামও চলে)
        $lists = [
            'category' => $allowed['ধরন'],
            'sex' => $allowed['লিঙ্গ'],
            'breed' => $allowed['জাত'],
            'acquisition_type' => $allowed['কীভাবে এল'],
            'status' => $allowed['অবস্থা'],
            'birth_date_estimated' => ['হ্যাঁ', 'না'],
        ];

        foreach ($lists as $field => $values) {
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('তালিকার বাইরের মান');
            $validation->setError('তালিকা থেকে বেছে নিন। অন্য কিছু লিখলে ইমপোর্টে চেনা না-ও যেতে পারে।');
            $validation->setFormula1('"' . implode(',', $values) . '"');

            $sheet->setDataValidation("{$letters[$field]}2:{$letters[$field]}{$max}", $validation);
        }

        // নির্দেশনা
        $help = $book->createSheet();
        $help->setTitle('নির্দেশনা');
        $help->getColumnDimension('A')->setWidth(28);
        $help->getColumnDimension('B')->setWidth(90);

        $lines = [
            ['গরুর তালিকা ইমপোর্ট', ''],
            ['', ''],
            ['কীভাবে', "'গরু' শিটে প্রথম সারির নিচে প্রতিটি সারিতে একটা করে গরু লিখুন। প্রথম সারি (কলামের নাম) বদলাবেন না।"],
            ['জরুরি কলাম', 'ট্যাগ নম্বর, ধরন, লিঙ্গ (* চিহ্নিত)। বাকি সব না দিলেও চলে।'],
            ['সীমা', 'একবারে সর্বোচ্চ ' . ($max - 1) . 'টি গরু। ট্যাগ আগে থেকে থাকলে সেই সারি বাদ যায়।'],
            ['', ''],
        ];

        foreach ($allowed as $title => $values) {
            $lines[] = [$title, implode(', ', $values)];
        }

        $lines = array_merge($lines, [
            ['', ''],
            ['তারিখ', '2024-05-10 বা 10/05/2024 লিখুন (Excel-এর তারিখও চলে)।'],
            ['টাকা', 'সংখ্যা লিখুন, যেমন 80000 (কমা ও বাংলা সংখ্যা চলে)।'],
            ['জন্মতারিখ আনুমানিক', 'সঠিক তারিখ না জানলে আনুমানিক দিয়ে "হ্যাঁ" লিখুন।'],
            ['কীভাবে এল', 'না দিলে "আগে থেকে ছিল" ধরা হয়। কেনা হলে কেনার তারিখ ও ক্রয়মূল্য, আগে থেকে ছিল হলে প্রারম্ভিক মূল্য দিন, না হলে লাভ-ক্ষতির হিসাব ঠিক আসবে না।'],
            ['মায়ের ট্যাগ', 'মায়ের ট্যাগ নম্বর (মা এই ফাইলে বা আগে থেকে খামারে থাকলে চলে)।'],
            ['অবস্থা', 'না দিলে "সক্রিয়"। মৃত বা সরানো হলে "বাদ পড়ার তারিখ" দিতে হবে। বিক্রিত গরু ইমপোর্ট হয় না।'],
            ['জাত', 'তালিকার বাইরের জাত লিখলে "অন্যান্য" ধরা হবে, আর আসল নামটা নোটে থাকবে।'],
        ]);

        foreach ($lines as $i => [$a, $b]) {
            $help->setCellValueExplicit('A' . ($i + 1), $a, DataType::TYPE_STRING);
            $help->setCellValueExplicit('B' . ($i + 1), $b, DataType::TYPE_STRING);
        }
        $help->getStyle('A1:A' . count($lines))->getFont()->setBold(true);
        $help->getStyle('B1:B' . count($lines))->getAlignment()->setWrapText(true);

        $book->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($book))->save('php://output');
        $content = (string) ob_get_clean();
        $book->disconnectWorksheets();

        return $content;
    }
}
