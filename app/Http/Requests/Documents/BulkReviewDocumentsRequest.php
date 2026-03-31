<?php

namespace App\Http\Requests\Documents;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReviewDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = tenant()?->id;
        $existsRule = Rule::exists('documents', 'id');

        if (is_string($tenantId) && $tenantId !== '') {
            $existsRule->where(fn ($query) => $query->where('tenant_id', $tenantId));
        }

        return [
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'integer', 'distinct', $existsRule],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_ids.required' => 'Select at least one document.',
            'document_ids.array' => 'The selected documents payload is invalid.',
            'document_ids.min' => 'Select at least one document.',
            'document_ids.*.distinct' => 'Documents may only be selected once per bulk action.',
            'document_ids.*.exists' => 'One or more selected documents are no longer available in this tenant.',
        ];
    }
}
