<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

class PhotoCompressionService
{
    // Uses intervention/image v4 directly (the Laravel facade package,
    // intervention/image-laravel, isn't installed). Imagick when the server
    // has it, otherwise GD; Hostinger ships GD.
    public static function available(): bool
    {
        return extension_loaded('imagick') || extension_loaded('gd');
    }

    // Never store an uncompressed original. Resize + webp at upload time,
    // so there's no separate "compress later" step to forget.
    public function store(UploadedFile $file, string $directory): string
    {
        if (! static::available()) {
            throw new RuntimeException('Photos can\'t be processed: this server\'s PHP has neither the GD nor the Imagick extension enabled.');
        }

        $manager = ImageManager::usingDriver(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class);
        $filename = $directory . '/' . Str::uuid() . '.webp';

        $encoded = $manager->decodePath($file->getRealPath())
            ->scaleDown(width: 1200)
            ->encode(new WebpEncoder(quality: 70));

        Storage::disk('public')->put($filename, (string) $encoded);

        return $filename;
    }
}
