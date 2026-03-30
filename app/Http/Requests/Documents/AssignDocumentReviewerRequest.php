<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignDocumentReviewerRequest extends FormRequest
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
        $existsRule = Rule::exists('users', 'id');

        if (is_string($tenantId) && $tenantId !== '') {
            $existsRule->where(fn ($query) => $query->where('tenant_id', $tenantId));
        }

        return [
            'assigned_to' => [
                'nullable',
                'integer',
                $existsRule,
            ],
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

                $assignedTo = $this->input('assigned_to');

                if ($assignedTo === null || $assignedTo === '') {
                    return;
                }

                if (! is_numeric($assignedTo)) {
                    return;
                }

                $assignee = User::query()->find((int) $assignedTo);

                if (! $assignee instanceof User || $assignee->tenant_id !== $document->tenant_id) {
                    return;
                }

                if (! $this->userHasRoleInTenant($assignee, 'associate', $document->tenant_id)) {
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
