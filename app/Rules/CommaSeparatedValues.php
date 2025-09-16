<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class CommaSeparatedValues implements ValidationRule
{
    public function __construct(protected array $allowedValues)
    {
    }


    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail("Field :attribute must be a string.");
            return;
        }

        $values = explode(',', $value);
        foreach ($values as $value) {
            if (!in_array($value, $this->allowedValues)) {
                $fail("Field :attribute must be a valid value. Allowed values are: " . implode(', ', $this->allowedValues));
                return;
            }
        }
    }
}
