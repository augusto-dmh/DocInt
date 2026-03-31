<?php

namespace App\Http\Requests\Documents;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkAssignDocumentReviewerRequest extends FormRequest
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
        $documentExistsRule = Rule::exists('documents', 'id');
        $userExistsRule = Rule::exists('users', 'id');

        if (is_string($tenantId) && $tenantId !== '') {
            $documentExistsRule->where(fn ($query) => $query->where('tenant_id', $tenantId));
            $userExistsRule->where(fn ($query) => $query->where('tenant_id', $tenantId));
        }

        return [
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['required', 'integer', 'distinct', $documentExistsRule],
            'assigned_to' => ['nullable', 'integer', $userExistsRule],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $assignedTo = $this->input('assigned_to');

                if (! is_numeric($assignedTo)) {
                    return;
                }

                $tenantId = tenant()?->id;
                $assignee = User::query()->find((int) $assignedTo);

                if (
                    ! $assignee instanceof User
                    || $assignee->tenant_id !== $tenantId
                ) {
                    return;
                }

                if (! $this->userHasRoleInTenant($assignee, 'associate', $tenantId)) {
                    $validator->errors()->add(
                        'assigned_to',
                        'The selected reviewer must be an associate.',
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
            'document_ids.required' => 'Select at least one document.',
            'document_ids.array' => 'The selected documents payload is invalid.',
            'document_ids.min' => 'Select at least one document.',
            'document_ids.*.distinct' => 'Documents may only be selected once per bulk action.',
            'document_ids.*.exists' => 'One or more selected documents are no longer available in this tenant.',
            'assigned_to.exists' => 'The selected reviewer must belong to the active tenant.',
        ];
    }

    protected function userHasRoleInTenant(User $user, string $role, ?string $tenantId): bool
    {
        if (! function_exists('getPermissionsTeamId') || ! function_exists('setPermissionsTeamId')) {
            return $user->hasRole($role);
        }

        $originalTeamId = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);

        try {
            $user->unsetRelation('roles');

            return $user->hasRole($role);
        } finally {
            setPermissionsTeamId($originalTeamId);
        }
    }
}
