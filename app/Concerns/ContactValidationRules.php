<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ContactValidationRules
{
    /**
     * Get the validation rules used to validate Philippine mobile numbers.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'regex:/^(\+639|09)\d{9}$/'];
    }

    /**
     * Get the validation rules used to validate a home address.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function addressRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate a birthdate. Renters must be adults.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function birthdateRules(): array
    {
        return ['nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()];
    }
}
