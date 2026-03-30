<?php

namespace App\Http\Requests\Documents;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentAnnotationRequest extends FormRequest
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
        return [
            'type' => ['required', Rule::in(['highlight', 'comment', 'note'])],
            'page_number' => ['required', 'integer', 'min:1'],
            'coordinates' => ['required', 'array'],
            'coordinates.x' => ['required', 'numeric', 'between:0,1'],
            'coordinates.y' => ['required', 'numeric', 'between:0,1'],
            'coordinates.width' => ['required', 'numeric', 'gt:0', 'max:1'],
            'coordinates.height' => ['required', 'numeric', 'gt:0', 'max:1'],
            'content' => [
                Rule::requiredIf(fn (): bool => in_array($this->string('type')->toString(), ['comment', 'note'], true)),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $coordinates = $this->input('coordinates', []);

                if (! is_array($coordinates)) {
                    return;
                }

                $x = is_numeric($coordinates['x'] ?? null) ? (float) $coordinates['x'] : null;
                $y = is_numeric($coordinates['y'] ?? null) ? (float) $coordinates['y'] : null;
                $width = is_numeric($coordinates['width'] ?? null) ? (float) $coordinates['width'] : null;
                $height = is_numeric($coordinates['height'] ?? null) ? (float) $coordinates['height'] : null;

                if ($x === null || $y === null || $width === null || $height === null) {
                    return;
                }

                if ($x + $width > 1 || $y + $height > 1) {
                    $validator->errors()->add(
                        'coordinates',
                        'Annotation boxes must stay within the visible PDF page.',
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
            'content.required' => 'Comments and notes require text.',
        ];
    }
}
