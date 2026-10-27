<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Services\ReportExcel;
use App\Services\ReportPdf;
use App\Services\ReportService;
use App\Support\Audit;
use App\Support\ReportDocuments;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        Farm $farm,
        string $report,
        string $format,
        ReportService $reports,
        ReportExcel $excel,
        ReportPdf $pdf,
    ) {
        Gate::authorize('manageFinance', $farm);

        $back = fn(string $message) => redirect()->route('farms.reports.index', $farm)->with('error', $message);

        if (! $reports->planAllows($farm, 'export') || ($report === 'cows' && ! $reports->planAllows($farm, 'cow_profit_report'))) {
            return $back('এই রিপোর্ট ডাউনলোডের সুবিধা আপনার প্ল্যানে নেই।');
        }

        $period = ReportPeriod::fromRequest($request);

        $doc = match ($report) {
            'cash-flow' => ReportDocuments::cashFlow($farm, $period, $reports->cashFlow($farm, $period)),
            'profit-loss' => ReportDocuments::profitLoss($farm, $period, $reports->profitLoss($farm, $period)),
            default => $this->cowDocument($request, $farm, $period, $reports),
        };

        $name = sprintf('genz-agro-%s-%s_%s.%s', $report, $period->fromDate(), $period->toDate(), $format);

        if ($format === 'pdf') {
            if (! $pdf->fontsReady()) {
                return $back('PDF-এর ফন্ট ফাইল নেই। ' . ReportPdf::REGULAR . ' ও ' . ReportPdf::BOLD . ' ফাইল দুটো storage/fonts ফোল্ডারে রাখুন। এখনকার জন্য রিপোর্ট পাতার "প্রিন্ট" থেকে PDF সংরক্ষণ করতে পারেন।');
            }

            try {
                $content = $pdf->build($doc);
            } catch (\Throwable $e) {
                report($e);

                return $back('PDF তৈরি করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন, বা "প্রিন্ট" থেকে PDF সংরক্ষণ করুন।');
            }

            $mime = 'application/pdf';
        } else {
            try {
                $content = $excel->build($doc);
            } catch (\Throwable $e) {
                report($e);

                return $back('Excel ফাইল তৈরি করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।');
            }

            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        Audit::log('exported', $farm->id, 'Report', null, 'রিপোর্ট: ' . $doc['title'], [], [], [
            'report' => $report,
            'format' => $format,
            'from' => $period->fromDate(),
            'to' => $period->toDate(),
        ]);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $name, ['Content-Type' => $mime]);
    }

    private function cowDocument(Request $request, Farm $farm, ReportPeriod $period, ReportService $reports): array
    {
        $report = $reports->cowProfitability($farm, $period);
        $view = $reports->cowRows($report, $request->query('status'), $request->query('sort'));

        return ReportDocuments::cows($farm, $period, $report, $view['rows'], $view['status']);
    }
}
