<?php

namespace App\Support\Security;

use Illuminate\Validation\Rules\Password;

class PasswordPolicyRules
{
    /**
     * Build the configured Password validation rule.
     */
    public static function rule(): Password
    {
        $min = (int) config('security.password_policy.min_length', 12);
        $max = (int) config('security.password_policy.max_length', 128);
        $mixedCase = (bool) config('security.password_policy.mixed_case', true);
        $letters = (bool) config('security.password_policy.letters', true);
        $numbers = (bool) config('security.password_policy.numbers', true);
        $symbols = (bool) config('security.password_policy.symbols', true);

        $rule = Password::min(max($min, 1));

        if ($max > 0) {
            $rule->max($max);
        }

        if ($mixedCase) {
            $rule->mixedCase();
        }

        if ($letters) {
            $rule->letters();
        }

        if ($numbers) {
            $rule->numbers();
        }

        if ($symbols) {
            $rule->symbols();
        }

        return $rule;
    }

    /**
     * Get password validation rules for creating a user.
     *
     * @return array<int, mixed>
     */
    public static function forCreation(): array
    {
        return ['required', 'string', static::rule()];
    }

    /**
     * Get password validation rules for updating a user.
     *
     * @return array<int, mixed>
     */
    public static function forUpdate(): array
    {
        return ['nullable', 'string', static::rule()];
    }
}
