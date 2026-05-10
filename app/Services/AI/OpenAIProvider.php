<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4-turbo-preview');
        $this->timeout = config('services.openai.timeout', 120);
        $this->maxTokens = config('services.openai.max_tokens', 4096);
    }

    public function complete(string $prompt, array $options = []): string
    {
        $cacheKey = $this->getCacheKey('complete', $prompt, $options);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
                'timeout' => $this->timeout,
            ]);

            $result = $response->choices[0]->message->content ?? '';
            Cache::put($cacheKey, $result, now()->addMinutes(10));
            return $result;
        } catch (\Exception $e) {
            Log::error('OpenAI Provider error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function completeJson(string $prompt, array $options = []): ?array
    {
        $jsonPrompt = $prompt . "\n\nReturn ONLY valid JSON, no additional text.";
        $response = $this->complete($jsonPrompt, $options);

        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        Log::warning('OpenAI Provider: Failed to parse JSON from response: ' . substr($response, 0, 200));
        return null;
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    protected function getCacheKey(string $method, string $prompt, array $options): string
    {
        return 'ai_provider:' . $this->getProviderName() . ':' . $method . ':' . md5($prompt . json_encode($options));
    }
}
