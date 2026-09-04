<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProfileImageService
{
    private const MAX_DIMENSION = 512;

    private const THUMBNAIL_DIMENSION = 192;

    private const JPEG_QUALITY = 82;

    private const THUMBNAIL_QUALITY = 78;

    /**
     * Store an optimized avatar and a small derivative for list/dashboard use.
     * The database continues to point at the primary avatar path, so existing
     * avatar URL contracts remain unchanged.
     */
    public function store(UploadedFile $file): string
    {
        $contents = @file_get_contents($file->getRealPath());
        $source = $contents === false || ! function_exists('imagecreatefromstring')
            ? false
            : @imagecreatefromstring($contents);

        // Keep uploads functional on hosts without GD or for legacy image
        // formats that GD cannot decode. The validation rules still apply.
        if ($source === false || ! function_exists('imagejpeg')) {
            return $file->store('avatars', 'public');
        }

        $path = 'avatars/'.Str::uuid().'.jpg';
        $thumbnailPath = self::thumbnailPath($path);
        $disk = Storage::disk('public');

        try {
            $disk->put($path, $this->encode($source, self::MAX_DIMENSION, self::JPEG_QUALITY));
            $disk->put($thumbnailPath, $this->encode($source, self::THUMBNAIL_DIMENSION, self::THUMBNAIL_QUALITY));

            return $path;
        } catch (Throwable $exception) {
            $disk->delete([$path, $thumbnailPath]);
            throw $exception;
        } finally {
            imagedestroy($source);
        }
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete([
            $path,
            self::thumbnailPath($path),
        ]);
    }

    public static function thumbnailPath(string $path): string
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));
        $directory = dirname($path);

        if ($directory === 'thumbs' || str_ends_with($directory, '/thumbs')) {
            return $path;
        }

        return ($directory === '.' ? '' : $directory.'/').'thumbs/'.basename($path);
    }

    private function encode($source, int $maxDimension, int $quality): string
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $canvas = imagecreatetruecolor($width, $height);

        // JPEG has no alpha channel. A white background preserves the visual
        // result for transparent PNG avatars.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($canvas, null, $quality);
        $encoded = ob_get_clean();
        imagedestroy($canvas);

        if ($encoded === false || $encoded === '') {
            throw new \RuntimeException('The profile image could not be encoded.');
        }

        return $encoded;
    }
}
