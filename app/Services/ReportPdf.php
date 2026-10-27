<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class ReportPdf
{
    public const REGULAR = 'HindSiliguri-Regular.ttf';
    public const BOLD = 'HindSiliguri-Bold.ttf';

    public function fontDir(): string
    {
        return storage_path('fonts');
    }

    public function fontsReady(): bool
    {
        return is_file($this->fontDir() . DIRECTORY_SEPARATOR . self::REGULAR)
            && is_file($this->fontDir() . DIRECTORY_SEPARATOR . self::BOLD);
    }

    public function build(array $doc): string
    {
        $html = view('reports.pdf.document', ['doc' => $doc])->render();

        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $config = (new ConfigVariables())->getDefaults();
        $fonts = (new FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => ($doc['landscape'] ?? false) ? 'A4-L' : 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'tempDir' => $tmp,
            'fontDir' => array_merge($config['fontDir'], [$this->fontDir()]),
            'fontdata' => $fonts['fontdata'] + [
                // useOTL: বাংলা যুক্তাক্ষর ও কার ঠিকমতো বসানোর জন্য
                'hindsiliguri' => ['R' => self::REGULAR, 'B' => self::BOLD, 'useOTL' => 0xFF],
            ],
            'default_font' => 'hindsiliguri',
            'default_font_size' => 10,
        ]);

        $mpdf->SetTitle($doc['title']);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
