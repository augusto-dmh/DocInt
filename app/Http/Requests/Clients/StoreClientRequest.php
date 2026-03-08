<?php

namespace App\Http\Requests\Clients;

use App\Concerns\ClientValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    use ClientValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->clientRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->clientValidationMessages();
    }
}
