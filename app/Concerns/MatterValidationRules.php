<?php

namespace App\Concerns;

use App\Enums\MatterStatus;
use App\Models\Client;
use App\Models\Matter;
use Illuminate\Validation\Rule;

trait MatterValidationRules
{
    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function matterRules(?int $matterId = null): array
    {
        return [
            'client_id' => [
                'required',
                Rule::exists(Client::class, 'id')->where('tenant_id', tenant()?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reference_number' => [
                'nullable',
                'string',
                'max:100',
                $matterId === null
                    ? Rule::unique(Matter::class)->where('tenant_id', tenant()?->id)
                    : Rule::unique(Matter::class)->ignore($matterId)->where('tenant_id', tenant()?->id),
            ],
            'status' => ['required', Rule::enum(MatterStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function matterValidationMessages(): array
    {
        return [
            'client_id.required' => 'Select a client for this matter.',
            'client_id.exists' => 'The selected client is not available in the active tenant.',
            'title.required' => 'A matter title is required.',
            'reference_number.unique' => 'This reference number is already in use for another matter in the active tenant.',
            'status.required' => 'Choose a matter status.',
        ];
    }
}
