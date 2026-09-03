<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidScheduleTimeWindow implements ValidationRule
{
    public function __construct(private readonly mixed $startTime) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->startTime !== null && substr((string) $this->startTime, 0, 5) === substr((string) $value, 0, 5)) {
            $fail('The schedule must have different start and end times.');
        }
    }
}
