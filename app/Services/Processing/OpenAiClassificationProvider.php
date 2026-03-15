<?php

namespace App\Services\Processing;

use App\Models\Document;
use App\Models\ExtractedData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenAiClassificationProvider implements ClassificationProvider
{
    public function __construct(
        public ClassificationRoutingResolver $classificationRoutingResolver,
        public ProviderCircuitBreaker $providerCircuitBreaker,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     type: string,
     *     confidence: float|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function classify(Document $document, ?ExtractedData $extractedData, array $payload): array
    {
        $classificationText = $this->resolveClassificationText($document, $extractedData);
        $apiKey = $this->resolveApiKey();

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = $this->resolveModel();
        $baseUrl = $this->resolveBaseUrl();
        $timeoutSeconds = $this->resolveTimeoutSeconds();

        $classificationResult = $this->providerCircuitBreaker->run(
            provider: 'openai',
            operation: function () use ($apiKey, $model, $baseUrl, $timeoutSeconds, $classificationText): array {
                $response = Http::timeout($timeoutSeconds)
                    ->acceptJson()
                    ->asJson()
                    ->withToken($apiKey)
                    ->post(
                        $baseUrl.'/chat/completions',
                        $this->requestPayload($model, $classificationText),
                    );

                $response->throw();

                $payload = $response->json();

                if (! is_array($payload)) {
                    throw new RuntimeException('OpenAI response payload is invalid.');
                }

                return $payload;
            },
            isTransient: fn (Throwable $throwable): bool => $this->isTransientFailure($throwable),
        );

        $rawContent = data_get($classificationResult, 'choices.0.message.content');

        if (! is_string($rawContent) || trim($rawContent) === '') {
            throw new RuntimeException('OpenAI response content is missing.');
        }

        $decoded = json_decode($rawContent, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI structured classification response is invalid JSON.');
        }

        $selectedLabel = is_string($decoded['label'] ?? null)
            ? strtolower(trim($decoded['label']))
            : null;
        $selectedScore = isset($decoded['confidence']) && is_numeric($decoded['confidence'])
            ? max(0.0, min(1.0, (float) $decoded['confidence']))
            : null;

        $resolvedType = $this->classificationRoutingResolver->resolveTypeHint(
            $selectedLabel,
            $document->file_name,
            $classificationText,
        );

        return [
            'provider' => 'openai',
            'type' => $resolvedType,
            'confidence' => $selectedScore,
            'metadata' => [
                'source' => 'openai.chat.completions',
                'model' => $model,
                'response_id' => is_string($classificationResult['id'] ?? null)
                    ? $classificationResult['id']
                    : null,
                'selected_label' => $selectedLabel,
                'selected_score' => $selectedScore,
            ],
        ];
    }

    protected function resolveClassificationText(Document $document, ?ExtractedData $extractedData): string
    {
        $text = is_string($extractedData?->extracted_text)
            ? trim($extractedData->extracted_text)
            : '';

        if ($text !== '') {
            return $text;
        }

        return trim($document->title.' '.$document->file_name);
    }

    protected function resolveApiKey(): string
    {
        return trim((string) config('processing.openai.api_key', ''));
    }

    protected function resolveModel(): string
    {
        return trim((string) config('processing.openai.model', 'gpt-4o-mini'));
    }

    protected function resolveBaseUrl(): string
    {
        $baseUrl = trim((string) config('processing.openai.base_url', 'https://api.openai.com/v1'));

        return rtrim($baseUrl, '/');
    }

    protected function resolveTimeoutSeconds(): int
    {
        $timeoutSeconds = (int) config('processing.openai.timeout_seconds', 15);

        return max(1, $timeoutSeconds);
    }

    /**
     * @return array{
     *     model: string,
     *     temperature: int,
     *     response_format: array{
     *         type: string,
     *         json_schema: array{
     *             name: string,
     *             strict: bool,
     *             schema: array<string, mixed>
     *         }
     *     },
     *     messages: list<array{role: string, content: string}>
     * }
     */
    protected function requestPayload(string $model, string $classificationText): array
    {
        return [
            'model' => $model,
            'temperature' => 0,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'docintern_document_classification',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'label' => [
                                'type' => 'string',
                                'enum' => ['contract', 'tax', 'invoice', 'general'],
                            ],
                            'confidence' => [
                                'type' => 'number',
                                'minimum' => 0,
                                'maximum' => 1,
                            ],
                        ],
                        'required' => ['label', 'confidence'],
                    ],
                ],
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Classify legal or business document text into one label: contract, tax, invoice, or general. Respond only with JSON matching the schema.',
                ],
                [
                    'role' => 'user',
                    'content' => $classificationText,
                ],
            ],
        ];
    }

    protected function isTransientFailure(Throwable $throwable): bool
    {
        if ($throwable instanceof ConnectionException) {
            return true;
        }

        if (! $throwable instanceof RequestException) {
            return false;
        }

        $statusCode = $throwable->response?->status();

        if ($statusCode === null) {
            return true;
        }

        return $statusCode === 429 || $statusCode >= 500;
    }
}
