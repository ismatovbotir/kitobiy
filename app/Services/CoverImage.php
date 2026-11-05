<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an uploaded photo into a small WebP cover on the covers disk. Encoding
 * it anew also drops the photo's metadata (camera, GPS location).
 */
class CoverImage
{
    private const int MAX_WIDTH = 600;

    private const int MAX_HEIGHT = 900;

    private const int WEBP_QUALITY = 80;

    /**
     * Returns the stored path, or null when the file cannot be read as an image.
     */
    public function store(UploadedFile $file): ?string
    {
        $photo = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($photo === false) {
            return null;
        }

        // Shrink first: rotating a full-size photo would hold two copies of it in memory.
        $angle = $this->rotationOf($file);
        $cover = $this->resized($photo, sideways: $angle % 180 !== 0);
        unset($photo);

        if ($angle !== 0) {
            $cover = imagerotate($cover, $angle, 0);
        }

        ob_start();
        imagewebp($cover, null, self::WEBP_QUALITY);
        $path = 'covers/'.Str::random(40).'.webp';

        return Storage::disk(config('catalog.cover_disk'))->put($path, (string) ob_get_clean()) ? $path : null;
    }

    /**
     * Phone cameras store the rotation in EXIF instead of turning the pixels.
     */
    private function rotationOf(UploadedFile $file): int
    {
        if ($file->getMimeType() !== 'image/jpeg') {
            return 0;
        }

        $exif = @exif_read_data($file->getRealPath());

        return match ($exif['Orientation'] ?? null) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };
    }

    /**
     * Fits the photo inside the cover size on white, so a transparent PNG never
     * turns black. A photo still to be turned sideways uses the limits swapped.
     */
    private function resized(GdImage $photo, bool $sideways): GdImage
    {
        $width = imagesx($photo);
        $height = imagesy($photo);
        $scale = $sideways
            ? min(1, self::MAX_HEIGHT / $width, self::MAX_WIDTH / $height)
            : min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $coverWidth = max(1, (int) round($width * $scale));
        $coverHeight = max(1, (int) round($height * $scale));

        $cover = imagecreatetruecolor($coverWidth, $coverHeight);
        imagefill($cover, 0, 0, imagecolorallocate($cover, 255, 255, 255));
        imagecopyresampled($cover, $photo, 0, 0, 0, 0, $coverWidth, $coverHeight, $width, $height);

        return $cover;
    }
}
