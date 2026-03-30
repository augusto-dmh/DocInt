<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Models\DocumentComment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentCommentRequest extends FormRequest
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
        $document = $this->route('document');
        $tenantId = $document instanceof Document ? $document->tenant_id : null;
        $existsRule = Rule::exists('document_comments', 'id');

        if (is_string($tenantId) && $tenantId !== '') {
            $existsRule->where(fn ($query) => $query->where('tenant_id', $tenantId));
        }

        return [
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', $existsRule],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $document = $this->route('document');

                if (! $document instanceof Document) {
                    return;
                }

                $parentId = $this->input('parent_id');

                if (! is_numeric($parentId)) {
                    return;
                }

                $parentComment = DocumentComment::query()->find((int) $parentId);

                if (! $parentComment instanceof DocumentComment) {
                    return;
                }

                if ($parentComment->document_id !== $document->id) {
                    $validator->errors()->add(
                        'parent_id',
                        'The selected parent comment must belong to this document.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => 'The selected parent comment must belong to the active tenant.',
        ];
    }
}
