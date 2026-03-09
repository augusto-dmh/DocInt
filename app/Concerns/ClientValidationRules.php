<?php

namespace App\Concerns;

use App\Models\Client;
use Illuminate\Validation\Rule;

trait ClientValidationRules
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function clientRules(?int $clientId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                $clientId === null
                    ? Rule::unique(Client::class)->where('tenant_id', tenant()?->id)
                    : Rule::unique(Client::class)->ignore($clientId)->where('tenant_id', tenant()?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function clientValidationMessages(): array
    {
        return [
            'name.required' => 'A client name is required.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email address is already in use for another client in the active tenant.',
            'notes.max' => 'Client notes may not be greater than 5000 characters.',
        ];
    }
}
