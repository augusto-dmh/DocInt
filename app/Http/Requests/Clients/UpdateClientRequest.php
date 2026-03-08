<?php

namespace App\Http\Requests\Clients;

use App\Concerns\ClientValidationRules;
use App\Models\Client;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
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
        /** @var Client $client */
        $client = $this->route('client');

        return $this->clientRules($client->id);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->clientValidationMessages();
    }
}
