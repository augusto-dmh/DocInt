<?php

namespace App\Support;

class DocumentExperienceGuardrails
{
    /**
     * @return array{
     *     themeKey: string,
     *     wrappers: array{root: string, hero: string, surface: string},
     *     typography: array{title: string, subtle: string, seal: string},
     *     motion: array{reveal: string, delay1: string, delay2: string}
     * }
     */
    public static function inertiaPayload(): array
    {
        return [
            'themeKey' => 'phase2-ledger-v1',
            'wrappers' => [
                'root' => 'documents-experience',
                'hero' => 'doc-hero',
                'surface' => 'doc-surface',
            ],
            'typography' => [
                'title' => 'doc-title',
                'subtle' => 'doc-subtle',
                'seal' => 'doc-seal',
            ],
            'motion' => [
                'reveal' => 'doc-fade-up',
                'delay1' => 'doc-delay-1',
                'delay2' => 'doc-delay-2',
            ],
        ];
    }
}
