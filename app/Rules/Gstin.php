<?php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** An Indian GSTIN: 15 characters, state code, PAN, entity number, "Z", check character. Blank is fine; GSTIN is always optional. */
class Gstin implements ValidationRule
{
    public const PATTERN = '/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public static function normalize(?string $value): ?string
    {
        $v = strtoupper(preg_replace('/\s+/', '', (string) $value));

        return $v === '' ? null : $v;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $v = self::normalize((string) $value);
        if ($v !== null && ! preg_match(self::PATTERN, $v)) {
            $fail('That is not a valid GSTIN. It has 15 characters, for example 19ABCDE1234F1Z5.');
        }
    }
}
