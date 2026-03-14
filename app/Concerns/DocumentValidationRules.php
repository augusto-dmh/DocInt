<?php

namespace App\Concerns;

trait DocumentValidationRules
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function documentStoreRules(): array
    {
        return [
            'title' => $this->documentTitleRules(),
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
                'max:102400',
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function documentUpdateRules(): array
    {
        return [
            'title' => $this->documentTitleRules(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function documentTitleRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<string, string>
     */
    protected function documentValidationMessages(): array
    {
        return [
            'title.required' => 'Provide a document title.',
            'file.required' => 'Choose a document file to upload.',
            'file.mimes' => 'Upload a PDF, Word, Excel, or image file.',
            'file.max' => 'Document uploads may not exceed 100 MB.',
        ];
    }
}
