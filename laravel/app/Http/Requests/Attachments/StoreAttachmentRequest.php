<?php

namespace App\Http\Requests\Attachments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttachmentRequest extends FormRequest
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
        $maxSizeKb = (int) config('erp_attachments.max_size_kb', 10240);

        return [
            'entity_type' => ['required', 'string', 'max:100', Rule::in($allowedEntities)],
            'entity_id' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:'.$maxSizeKb],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
