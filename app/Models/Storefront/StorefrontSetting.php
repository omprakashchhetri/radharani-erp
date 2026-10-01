<?php
namespace App\Models\Storefront;

use Illuminate\Database\Eloquent\Model;

/**
 * Shop details shown on the website. Unset keys fall back to
 * config('storefront.defaults'), so the site renders before anyone has
 * filled the Website Settings screen in.
 */
class StorefrontSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    private static ?array $values = null;

    public static function get(string $key): ?string
    {
        return static::values()[$key] ?? null;
    }

    // A key that was never saved uses its default; one saved empty stays
    // empty (e.g. a cleared social link hides its icon).
    public static function values(): array
    {
        return static::$values ??= array_merge(
            config('storefront.defaults', []),
            array_map(fn ($v) => (string) $v, static::query()->pluck('value', 'key')->all()),
        );
    }

    // wa.me link to the shop's WhatsApp with a pre-filled message. Built
    // server-side so it survives Livewire re-renders (the site's [data-wa]
    // links are only filled in once, on page load).
    public static function whatsappUrl(string $message): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', (string) static::get('whatsapp')).'?text='.rawurlencode($message);
    }

    public static function put(array $values, ?int $userId): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        }
        static::$values = null;
    }
}
