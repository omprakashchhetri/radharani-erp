<?php
namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Turns a stored image reference into a URL. Uploaded photos are paths on
 * the public disk (written by PhotoCompressionService); a full http(s) URL
 * is passed through untouched, which only the demo seeder uses.
 */
class StorefrontImage
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return preg_match('#^https?://#i', $path) ? $path : Storage::disk('public')->url($path);
    }

    // Unsplash URLs (demo data, the site's own marketing photos) get the
    // crop/size parameters the design was built with; uploaded photos are
    // already resized to 1200px WebP on upload and are returned as they are.
    public static function sized(?string $url, int $w, ?int $h = null, int $q = 78): string
    {
        if (! $url) {
            return '';
        }
        if (preg_match('/^\d{10,}-[0-9a-f]+$/', $url)) {
            $url = 'https://images.unsplash.com/photo-'.$url;
        }
        if (! str_starts_with($url, 'https://images.unsplash.com/')) {
            return $url;
        }

        return strtok($url, '?').'?auto=format&fit=crop&w='.$w.($h ? '&h='.$h : '').'&q='.$q;
    }
}
