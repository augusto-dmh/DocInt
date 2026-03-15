<?php

namespace App\Support;

use InvalidArgumentException;

class ProcessingRuntimeConfigValidator
{
    public function validateOrFail(): void
    {
        $issues = [
            ...$this->exactContractIssues(),
            ...$this->nonEmptyContractIssues(),
        ];

        if ($issues === []) {
            return;
        }

        throw new InvalidArgumentException(
            "Development runtime configuration is invalid:\n- ".implode("\n- ", $issues),
        );
    }

    /**
     * @return list<string>
     */
    protected function exactContractIssues(): array
    {
        $issues = [];
        $exactContracts = config('processing.runtime_required_contract.exact', []);

        if (! is_array($exactContracts)) {
            return $issues;
        }

        foreach ($exactContracts as $contract) {
            if (! is_array($contract)) {
                continue;
            }

            $configPath = trim((string) ($contract['path'] ?? ''));
            $envKey = trim((string) ($contract['env'] ?? ''));
            $expected = strtolower(trim((string) ($contract['expected'] ?? '')));

            if ($configPath === '' || $envKey === '' || $expected === '') {
                continue;
            }

            $actual = strtolower(trim((string) config($configPath)));

            if ($actual !== $expected) {
                $issues[] = sprintf('%s must be set to [%s].', $envKey, $expected);
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    protected function nonEmptyContractIssues(): array
    {
        $issues = [];
        $requiredContracts = config('processing.runtime_required_contract.non_empty', []);

        if (! is_array($requiredContracts)) {
            return $issues;
        }

        foreach ($requiredContracts as $contract) {
            if (! is_array($contract)) {
                continue;
            }

            $configPath = trim((string) ($contract['path'] ?? ''));
            $envKey = trim((string) ($contract['env'] ?? ''));

            if ($configPath === '' || $envKey === '') {
                continue;
            }

            $value = config($configPath);

            if (is_string($value) && trim($value) !== '') {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                continue;
            }

            $issues[] = sprintf('%s must be set for development runtime.', $envKey);
        }

        return $issues;
    }
}
