<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Services\FarmDataExport;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DataExportController extends Controller
{
    public function show(Farm $farm)
    {
        Gate::authorize('exportData', $farm);

        return view('data-export.show', ['farm' => $farm]);
    }

    public function download(Request $request, Farm $farm, FarmDataExport $export)
    {
        Gate::authorize('exportData', $farm);

        // অন্য সাইট থেকে লুকিয়ে এই ভারী কাজ চালানো আটকানো (ব্রাউজার এই header পাঠায়)
        abort_if($request->headers->get('Sec-Fetch-Site') === 'cross-site', 403);

        $withPhotos = $request->boolean('photos');

        try {
            $zip = $export->build($farm, $withPhotos);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('farms.data-export.show', $farm)
                ->with('error', 'ডেটা এক্সপোর্ট করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।');
        }

        Audit::log('exported', $farm->id, 'Report', null, 'খামারের সব ডেটা (ZIP)', [], [], [
            'photos' => $withPhotos ? 'yes' : 'no',
        ]);

        return response()
            ->download($zip, sprintf('genz-agro-farm-%d-data-%s.zip', $farm->id, now()->format('Ymd')), [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }
}
