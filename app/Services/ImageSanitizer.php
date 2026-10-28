<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ছবি আবার এনকোড করে সংরক্ষণ করে:
 * EXIF-এর GPS অবস্থান ও অন্য মেটাডেটা মুছে যায়, ছবির ভেতরে লুকানো কোড টিকে না, আকার ছোট হয়, নাম এলোমেলো হয়।
 */
class ImageSanitizer
{
    private const MAX_PIXELS = 25_000_000;

    public function store(UploadedFile $file, string $dir, string $disk = 'public', int $maxSide = 1600): string
    {
        return $this->storeFromPath((string) $file->getRealPath(), $dir, $disk, $maxSide);
    }

    public function storeFromPath(string $path, string $dir, string $disk = 'public', int $maxSide = 1600): string
    {
        $info = ($path !== '' && is_file($path)) ? @getimagesize($path) : false;
        $type = $info[2] ?? null;

        if (
            ! $info
            || ! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < 1 || $info[1] < 1
            || $info[0] * $info[1] > self::MAX_PIXELS
        ) {
            throw $this->invalid();
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        };

        if (! $source) {
            throw $this->invalid();
        }

        if ($type === IMAGETYPE_JPEG) {
            $source = $this->orient($source, $path);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // সাদা পটভূমিতে নতুন ছবি (PNG-র স্বচ্ছ অংশ কালো না হয়ে সাদা হয়)
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $binary = (string) ob_get_clean();

        // "c_" উপসর্গ মানে ছবিটা পরিষ্কার করা হয়েছে (photos:sanitize এগুলো আবার ছোঁয় না)
        $name = trim($dir, '/') . '/c_' . Str::random(32) . '.jpg';
        Storage::disk($disk)->put($name, $binary);

        return $name;
    }

    /** মোবাইলে তোলা ছবির EXIF ঘোরানোর তথ্য কাজে লাগিয়ে সোজা করা (মেটাডেটা মুছে ফেলার আগে) */
    private function orient(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'photo' => 'ছবিটি পড়া যায়নি বা খুব বড়। অন্য একটি JPG, PNG বা WebP ছবি দিন।',
        ]);
    }
}
