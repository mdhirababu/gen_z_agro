<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExcel
{
    public function build(array $doc): string
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('Gen Z Agro')->setTitle($doc['title']);

        $sheet = $book->getActiveSheet();
        $sheetTitle = trim((string) preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $doc['title']));
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31) ?: 'Report');

        $this->text($sheet, 'A1', $doc['title']);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $this->text($sheet, 'A2', $doc['farm']);
        $this->text($sheet, 'A3', $doc['period']);

        $row = 5;
        $maxCols = 2;

        foreach ($doc['sections'] as $section) {
            $cols = count($section['columns']);
            $maxCols = max($maxCols, $cols);
            $last = Coordinate::stringFromColumnIndex($cols);
            $money = $section['money'] ?? [];
            $ints = $section['int'] ?? [];

            $this->text($sheet, "A{$row}", $section['heading']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $row++;

            // কলামের নাম
            $headRow = $row;
            foreach ($section['columns'] as $i => $label) {
                $this->text($sheet, Coordinate::stringFromColumnIndex($i + 1) . $row, $label);
            }
            $head = "A{$row}:{$last}{$row}";
            $sheet->getStyle($head)->getFont()->setBold(true);
            $sheet->getStyle($head)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E6F4EA');
            $row++;

            // সারি: সংখ্যা সংখ্যা হিসেবে, লেখা সবসময় লেখা হিসেবে (সূত্র হিসেবে নয়)
            $start = $row;
            foreach ($section['rows'] as $cells) {
                foreach (array_values($cells) as $i => $cell) {
                    $coord = Coordinate::stringFromColumnIndex($i + 1) . $row;

                    if (is_int($cell) || is_float($cell)) {
                        $sheet->setCellValueExplicit($coord, $cell, DataType::TYPE_NUMERIC);
                    } else {
                        $this->text($sheet, $coord, (string) ($cell ?? ''));
                    }
                }
                $row++;
            }
            $end = $row - 1;

            // টাকা ও সংখ্যার কলামের ফরম্যাট ও ডান-সারি
            foreach (array_merge($money, $ints) as $i) {
                $letter = Coordinate::stringFromColumnIndex($i + 1);
                $range = "{$letter}{$headRow}:{$letter}{$end}";
                $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if ($end >= $start) {
                    $sheet->getStyle("{$letter}{$start}:{$letter}{$end}")->getNumberFormat()
                        ->setFormatCode(in_array($i, $money, true) ? '#,##0.00' : '0');
                }
            }

            $sheet->getStyle("A{$headRow}:{$last}{$end}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('B7D3C0');

            if (($section['total'] ?? false) && $end >= $start) {
                $sheet->getStyle("A{$end}:{$last}{$end}")->getFont()->setBold(true);
                $sheet->getStyle("A{$end}:{$last}{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F4FAF6');
            }

            $row++; // অংশের মাঝে ফাঁকা সারি
        }

        if (! empty($doc['notes'])) {
            $this->text($sheet, "A{$row}", 'টীকা');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;

            foreach ($doc['notes'] as $note) {
                $this->text($sheet, "A{$row}", $note);
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
                $row++;
            }
        }

        $widths = $doc['widths'] ?? [];
        for ($i = 1; $i <= $maxCols; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))
                ->setWidth($widths[$i - 1] ?? ($i === 1 ? 34 : 16));
        }

        $writer = new Xlsx($book);

        ob_start();
        $writer->save('php://output');
        $content = (string) ob_get_clean();

        $book->disconnectWorksheets();

        return $content;
    }

    /** লেখা সবসময় STRING হিসেবে বসে, যাতে "=...", "+...", "@..." দিয়ে শুরু হলেও সূত্র না হয় */
    private function text(Worksheet $sheet, string $coord, string $value): void
    {
        $sheet->setCellValueExplicit($coord, $value, DataType::TYPE_STRING);
    }
}
