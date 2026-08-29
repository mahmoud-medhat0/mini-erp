<?php

namespace App\Http\Requests\Attachments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedEntities = array_keys(config('erp_attachments.entities', []));

        return [
            'entity_type' => ['required', 'string', 'max:100', Rule::in($allowedEntities)],
            'entity_id' => ['required', 'string', 'max:100'],
        ];
    }
}
