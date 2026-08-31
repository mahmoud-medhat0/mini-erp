<?php

namespace App\Http\Requests\Settings;

use App\Support\Security\PasswordPolicyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('users.configure') || $user->can('settings.configure'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => PasswordPolicyRules::forCreation(),
            'locale' => ['nullable', 'string', Rule::in(['en', 'ar'])],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }
}
