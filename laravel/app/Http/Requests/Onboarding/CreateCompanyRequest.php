<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'array'],
            'company.name' => ['required', 'array'],
            'company.name.en' => ['required', 'string', 'max:255'],
            'company.name.ar' => ['required', 'string', 'max:255'],
            'company.base_currency' => ['sometimes', 'string', 'size:3', 'exists:currency,code'],
            'branch' => ['required', 'array'],
            'branch.code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'branch.name' => ['required', 'array'],
            'branch.name.en' => ['required', 'string', 'max:255'],
            'branch.name.ar' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{
     *     company: array{name: array{en: string, ar: string}, base_currency?: string},
     *     branch: array{code: string, name: array{en: string, ar: string}}
     * }
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'company' => [
                'name' => [
                    'en' => $validated['company']['name']['en'],
                    'ar' => $validated['company']['name']['ar'],
                ],
                ...isset($validated['company']['base_currency'])
                    ? ['base_currency' => $validated['company']['base_currency']]
                    : [],
            ],
            'branch' => [
                'code' => $validated['branch']['code'],
                'name' => [
                    'en' => $validated['branch']['name']['en'],
                    'ar' => $validated['branch']['name']['ar'],
                ],
            ],
        ];
    }
}
