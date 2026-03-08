<?php

namespace App\Http\Requests\Matters;

use App\Concerns\MatterValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMatterRequest extends FormRequest
{
    use MatterValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->matterRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->matterValidationMessages();
    }
}
