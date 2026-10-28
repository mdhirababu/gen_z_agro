<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/** বিশাল বা ভুয়া ফাইল মেমোরি না ভরতে: শুধু প্রথম কয়েকশো সারি ও ২০টা কলাম পড়া হয় */
class CowImportReadFilter implements IReadFilter
{
    public function __construct(private int $maxRow, private int $maxColumn = 20) {}

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row <= $this->maxRow
            && Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumn;
    }
}
