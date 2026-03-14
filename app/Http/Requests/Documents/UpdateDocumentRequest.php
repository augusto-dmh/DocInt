<?php

namespace App\Http\Requests\Documents;

use App\Concerns\DocumentValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
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
        return $this->documentUpdateRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->documentValidationMessages();
    }
}
