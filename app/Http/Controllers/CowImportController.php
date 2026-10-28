<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use App\Models\User;
use App\Services\CowImportReader;
use App\Services\CowImportService;
use App\Services\CowImportTemplate;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CowImportController extends Controller
{
    public function create(Farm $farm, CowImportService $service)
    {
        Gate::authorize('importCows', $farm);

        return view('cows.import', [
            'farm' => $farm,
            'slots' => $farm->cowSlotsLeft(),
            'maxRows' => (int) config('farm.import.max_rows', 500),
            'allowed' => $service->allowed(),
        ]);
    }

    public function template(Farm $farm, CowImportTemplate $template)
    {
        Gate::authorize('importCows', $farm);

        $content = $template->build();

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'gorur-talika-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** ফাইল পড়ে ক্যাশে রাখে, তারপর প্রিভিউ পাতায় পাঠায় (এখনো কিছু সংরক্ষণ হয় না) */
    public function upload(Request $request, Farm $farm, CowImportReader $reader, CowImportService $service)
    {
        Gate::authorize('importCows', $farm);

        $request->validate([
            'file' => ['required', 'file', 'max:' . (int) config('farm.import.max_kb', 2048), 'extensions:xlsx,csv'],
        ], [
            'file.required' => 'একটা ফাইল বেছে নিন।',
            'file.max' => 'ফাইল খুব বড় (সর্বোচ্চ ২ MB)।',
            'file.extensions' => 'শুধু .xlsx বা .csv ফাইল দেওয়া যাবে।',
        ], ['file' => 'ফাইল']);

        $file = $request->file('file');

        $read = $reader->read((string) $file->getRealPath(), $file->getClientOriginalExtension());

        if ($read['error']) {
            throw ValidationException::withMessages(['file' => $read['error']]);
        }

        $parsed = $service->parse($read['headers'], $read['rows']);

        if ($parsed['error']) {
            throw ValidationException::withMessages(['file' => $parsed['error']]);
        }

        if ($parsed['items'] === []) {
            throw ValidationException::withMessages(['file' => 'ফাইলে কোনো গরুর সারি পাওয়া যায়নি।']);
        }

        $token = Str::random(40);
        Cache::put($this->key($farm, $request->user(), $token), $parsed['items'], now()->addMinutes(30));

        return redirect()->route('farms.cows.import.preview', [$farm, $token]);
    }

    public function preview(Request $request, Farm $farm, string $token, CowImportService $service)
    {
        Gate::authorize('importCows', $farm);

        $items = Cache::get($this->key($farm, $request->user(), $token));

        if ($items === null) {
            return $this->expired($farm);
        }

        return view('cows.import-preview', [
            'farm' => $farm,
            'token' => $token,
            'analysis' => $service->analyze($farm, $items),
        ]);
    }

    public function store(Request $request, Farm $farm, string $token, CowImportService $service)
    {
        Gate::authorize('importCows', $farm);

        $key = $this->key($farm, $request->user(), $token);
        $items = Cache::get($key);

        if ($items === null) {
            return $this->expired($farm);
        }

        try {
            $result = $service->import($farm, $request->user(), $items);
        } catch (ValidationException $e) {
            return redirect()->route('farms.cows.import.preview', [$farm, $token])->withErrors($e->errors());
        }

        Cache::forget($key);

        Audit::log('imported', $farm->id, 'Cow', null, "গরুর তালিকা ইমপোর্ট: {$result['created']}টি", [], [], [
            'created' => $result['created'],
            'exists' => $result['exists'],
            'errors' => $result['error'],
        ]);

        $message = "{$result['created']}টি গরু যোগ হয়েছে।";
        if ($result['exists'] > 0) {
            $message .= " {$result['exists']}টি আগেই ছিল, বাদ গেছে।";
        }
        if ($result['error'] > 0) {
            $message .= " {$result['error']}টি সারিতে ভুল ছিল, বাদ গেছে। ঠিক করে আবার ফাইল দিন।";
        }

        return redirect()->route('farms.cows.index', $farm)->with('success', $message);
    }

    private function expired(Farm $farm)
    {
        return redirect()->route('farms.cows.import', $farm)
            ->with('error', 'প্রিভিউয়ের সময় শেষ হয়ে গেছে বা ইমপোর্ট আগেই হয়ে গেছে। ফাইলটা আবার দিন।');
    }

    /** ক্যাশের চাবিতে খামার ও ব্যবহারকারী আছে, তাই অন্য কারো প্রিভিউ খোলা যায় না */
    private function key(Farm $farm, User $user, string $token): string
    {
        return "cow-import:{$farm->id}:{$user->id}:{$token}";
    }
}
