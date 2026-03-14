<?php

namespace App\Http\Requests\Documents;

use App\Concerns\DocumentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    use DocumentValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->documentStoreRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->documentValidationMessages();
    }
}
