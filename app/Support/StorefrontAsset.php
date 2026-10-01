<?php
namespace App\Support;

/**
 * URLs for the storefront's static files in public/storefront, with the
 * file's modification time appended so a redeploy never serves a stale
 * cached copy (these files don't go through Vite).
 */
class StorefrontAsset
{
    public static function url(string $path): string
    {
        $file = public_path('storefront/'.$path);

        return asset('storefront/'.$path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
