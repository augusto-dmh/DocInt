<?php

namespace App\Http\Requests\Matters;

use App\Concerns\MatterValidationRules;
use App\Models\Matter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMatterRequest extends FormRequest
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
        /** @var Matter $matter */
        $matter = $this->route('matter');

        return $this->matterRules($matter->id);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->matterValidationMessages();
    }
}
