<?php

namespace App\Console\Commands;

use App\Services\ImageSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PhotosSanitize extends Command
{
    protected $signature = 'photos:sanitize {--dry : কিছু না বদলে শুধু গুনে দেখায়}';

    protected $description = 'আগে আপলোড হওয়া গরুর ছবি আবার এনকোড করে GPS ও অন্য মেটাডেটা মুছে ফেলে';

    public function handle(ImageSanitizer $sanitizer): int
    {
        $disk = Storage::disk('public');
        $done = 0;
        $skipped = 0;
        $failed = 0;

        DB::table('cows')
            ->whereNotNull('photo_path')
            ->orderBy('id')
            ->chunkById(100, function ($cows) use ($sanitizer, $disk, &$done, &$skipped, &$failed) {
                foreach ($cows as $cow) {
                    $path = $cow->photo_path;

                    // আগেই পরিষ্কার করা, বা ফাইলটা আর নেই
                    if (str_starts_with(basename($path), 'c_') || ! $disk->exists($path)) {
                        $skipped++;
                        continue;
                    }

                    if ($this->option('dry')) {
                        $done++;
                        continue;
                    }

                    $tmp = tempnam(sys_get_temp_dir(), 'ph');

                    try {
                        file_put_contents($tmp, $disk->get($path));

                        $new = $sanitizer->storeFromPath($tmp, dirname($path));

                        // আগে নতুন ছবি সংরক্ষণ, তারপর রেকর্ড বদল, সবশেষে পুরোনোটা মোছা
                        DB::table('cows')->where('id', $cow->id)->update(['photo_path' => $new]);
                        $disk->delete($path);

                        $done++;
                    } catch (ValidationException) {
                        $failed++;
                    } finally {
                        @unlink($tmp);
                    }
                }
            });

        $verb = $this->option('dry') ? 'পরিষ্কার করা হবে' : 'পরিষ্কার করা হয়েছে';
        $this->info("{$verb}: {$done}টি | বাদ (আগেই পরিষ্কার বা ফাইল নেই): {$skipped}টি | ব্যর্থ: {$failed}টি");

        return self::SUCCESS;
    }
}
