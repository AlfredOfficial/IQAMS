<?php

namespace App\Rules;

use App\Models\Section;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SectionBelongsToCourse implements ValidationRule
{
    public function __construct(private readonly int|string|null $courseId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! Section::query()->whereKey($value)->where('course_id', $this->courseId)->exists()) {
            $fail('The selected section does not belong to the selected course.');
        }
    }
}
