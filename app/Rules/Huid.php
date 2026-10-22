<?php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A HUID is at most 6 letters or digits (17 Oct). Blank is fine; a piece without one gets a shop code. */
class Huid implements ValidationRule
{
    public const PATTERN = '/^[A-Z0-9]{1,6}$/';
    public const MESSAGE = 'A HUID is at most 6 letters or digits.';

    public static function normalize(?string $value): ?string
    {
        $v = strtoupper(preg_replace('/\s+/', '', (string) $value));

        return $v === '' ? null : $v;
    }

    public static function isValid(?string $value): bool
    {
        $v = self::normalize($value);

        return $v === null || (bool) preg_match(self::PATTERN, $v);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail(self::MESSAGE);
        }
    }
}
