<?php

namespace App\Services\Processing;

use App\Models\Document;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class OpenAiOcrProvider implements OcrProvider
{
    public function __construct(
        public ClassificationRoutingResolver $classificationRoutingResolver,
        public ProviderCircuitBreaker $providerCircuitBreaker,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     extracted_text: string|null,
     *     payload: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     classification_hint: string|null
     * }
     */
    public function extract(Document $document, array $payload): array
    {
        $apiKey = $this->resolveApiKey();

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $sourceText = $this->resolveSourceText($document, $payload);
        $model = $this->resolveModel();
        $baseUrl = $this->resolveBaseUrl();
        $timeoutSeconds = $this->resolveTimeoutSeconds();

        $ocrResult = $this->providerCircuitBreaker->run(
            provider: 'openai',
            operation: function () use ($apiKey, $sourceText, $model, $baseUrl, $timeoutSeconds, $document): array {
                $response = Http::timeout($timeoutSeconds)
                    ->acceptJson()
                    ->asJson()
                    ->withToken($apiKey)
                    ->post(
                        $baseUrl.'/chat/completions',
                        $this->requestPayload(
                            model: $model,
                            document: $document,
                            sourceText: $sourceText,
                        ),
                    );

                $response->throw();

                $responsePayload = $response->json();

                if (! is_array($responsePayload)) {
                    throw new RuntimeException('OpenAI OCR response payload is invalid.');
                }

                return $responsePayload;
            },
            isTransient: fn (Throwable $throwable): bool => $this->isTransientFailure($throwable),
        );

        $rawContent = data_get($ocrResult, 'choices.0.message.content');

        if (! is_string($rawContent) || trim($rawContent) === '') {
            throw new RuntimeException('OpenAI OCR response content is missing.');
        }

        $decoded = json_decode($rawContent, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI OCR structured response is invalid JSON.');
        }

        $extractedText = is_string($decoded['extracted_text'] ?? null)
            ? trim($decoded['extracted_text'])
            : '';
        $lines = $this->normalizeLines($decoded['lines'] ?? null);
        $classificationHint = $this->classificationRoutingResolver->resolveTypeHint(
            is_string($decoded['classification_hint'] ?? null) ? $decoded['classification_hint'] : null,
            $document->file_name,
            $extractedText === '' ? null : $extractedText,
        );

        return [
            'provider' => 'openai',
            'extracted_text' => $extractedText === '' ? null : $extractedText,
            'payload' => [
                'lines' => $lines,
                'line_count' => count($lines),
                'source_characters' => mb_strlen($sourceText),
                'response_id' => is_string($ocrResult['id'] ?? null) ? $ocrResult['id'] : null,
            ],
            'metadata' => [
                'source' => 'openai.chat.completions',
                'model' => $model,
                'classification_hint' => $classificationHint,
            ],
            'classification_hint' => $classificationHint,
        ];
    }

    protected function resolveApiKey(): string
    {
        return trim((string) config('processing.openai.api_key', ''));
    }

    protected function resolveModel(): string
    {
        $ocrModel = trim((string) config('processing.openai.ocr_model', ''));

        if ($ocrModel !== '') {
            return $ocrModel;
        }

        return trim((string) config('processing.openai.model', 'gpt-4o-mini'));
    }

    protected function resolveBaseUrl(): string
    {
        $baseUrl = trim((string) config('processing.openai.base_url', 'https://api.openai.com/v1'));

        return rtrim($baseUrl, '/');
    }

    protected function resolveTimeoutSeconds(): int
    {
        $timeoutSeconds = (int) config('processing.openai.timeout_seconds', 30);

        return max(1, $timeoutSeconds);
    }

    /**
     * @return list<string>
     */
    protected function normalizeLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalizedLines = [];

        foreach ($lines as $line) {
            if (! is_string($line)) {
                continue;
            }

            $normalizedLine = trim($line);

            if ($normalizedLine === '') {
                continue;
            }

            $normalizedLines[] = $normalizedLine;
        }

        return $normalizedLines;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveSourceText(Document $document, array $payload): string
    {
        $metadata = is_array($payload['metadata'] ?? null)
            ? $payload['metadata']
            : [];

        $sourceText = is_string($metadata['ocr_source_text'] ?? null)
            ? trim($metadata['ocr_source_text'])
            : '';

        if ($sourceText === '') {
            $sourceText = trim($this->readTextFromS3($document));
        }

        $sourceText = $this->normalizeSourceText($sourceText);

        if ($sourceText !== '' && $this->isLikelyBinaryNoise($sourceText)) {
            $sourceText = '';
        }

        if ($sourceText === '') {
            $sourceText = trim($document->title.' '.$document->file_name);
        }

        if ($sourceText === '') {
            throw new RuntimeException('Document source text is unavailable for OpenAI OCR.');
        }

        return mb_substr($sourceText, 0, 3000);
    }

    protected function readTextFromS3(Document $document): string
    {
        try {
            /** @var string $contents */
            $contents = Storage::disk('s3')->get($document->file_path);
        } catch (Throwable $throwable) {
            throw new RuntimeException(
                sprintf('Unable to read document [%s] from S3 for OpenAI OCR.', $document->id),
                previous: $throwable,
            );
        }

        $normalized = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $contents);

        return is_string($normalized) ? trim($normalized) : '';
    }

    protected function normalizeSourceText(string $sourceText): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sourceText));

        return is_string($normalized) ? trim($normalized) : '';
    }

    protected function isLikelyBinaryNoise(string $sourceText): bool
    {
        $length = strlen($sourceText);

        if ($length === 0) {
            return true;
        }

        preg_match_all('/[A-Za-z0-9]/', $sourceText, $matches);
        $alphanumericCount = count($matches[0]);

        return ($alphanumericCount / $length) < 0.2;
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
    protected function requestPayload(string $model, Document $document, string $sourceText): array
    {
        $documentContext = json_encode([
            'title' => $document->title,
            'file_name' => $document->file_name,
            'mime_type' => $document->mime_type,
        ], JSON_THROW_ON_ERROR);

        return [
            'model' => $model,
            'temperature' => 0,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'docintern_document_ocr',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'extracted_text' => [
                                'type' => 'string',
                            ],
                            'classification_hint' => [
                                'type' => 'string',
                                'enum' => ['contract', 'tax', 'invoice', 'general'],
                            ],
                            'lines' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string',
                                ],
                            ],
                        ],
                        'required' => ['extracted_text', 'classification_hint', 'lines'],
                    ],
                ],
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Extract readable text from the provided document content and infer the best classification hint among contract, tax, invoice, or general. Respond only with JSON matching the schema.',
                ],
                [
                    'role' => 'user',
                    'content' => "Document context: {$documentContext}\n\nDocument content:\n{$sourceText}",
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
