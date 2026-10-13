<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Australian mobile number: 10 digits starting with 04 (spaces allowed), or +61 4xx format.
 */
class MobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $number = preg_replace('/[\s\-()]/', '', (string) $value);

        if (str_starts_with($number, '+61')) {
            $number = '0'.substr($number, 3);
        }

        if (!preg_match('/^04\d{8}$/', $number)) {
            $fail('validation.mobile_number')->translate();
        }
    }
}
