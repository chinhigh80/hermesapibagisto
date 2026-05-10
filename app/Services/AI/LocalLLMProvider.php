<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LocalLLMProvider implements AIProviderInterface
{
    protected string $apiUrl;
    protected string $model;
    protected int $timeout;
    protected int $maxTokens;

    public function __construct()
    {
        $this->apiUrl = config('services.local_llm.api_url', 'http://localhost:8080/v1');
        $this->model = config('services.local_llm.model', 'local-model');
        $this->timeout = config('services.local_llm.timeout', 120);
        $this->maxTokens = config('services.local_llm.max_tokens', 4096);
    }

    public function complete(string $prompt, array $options = []): string
    {
        $cacheKey = $this->getCacheKey('complete', $prompt, $options);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->withTimeout($this->timeout)
            ->post($this->apiUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            ]);

            if ($response->successful()) {
                $result = $response->json()['choices'][0]['message']['content'] ?? '';
                Cache::put($cacheKey, $result, now()->addMinutes(10));
                return $result;
            }

            Log::error('Local LLM API error: ' . $response->body());
            throw new \RuntimeException('Local LLM API request failed: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('Local LLM Provider error: ' . $e->getMessage());
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

        Log::warning('Local LLM Provider: Failed to parse JSON from response: ' . substr($response, 0, 200));
        return null;
    }

    public function getProviderName(): string
    {
        return 'local_llm';
    }

    public function isAvailable(): bool
    {
        // For local, we assume it's available if the URL is set and we can reach it? 
        // We'll do a simple check: if the URL is set, we consider it available.
        // In production, you might want to ping the endpoint.
        return !empty($this->apiUrl);
    }

    protected function getCacheKey(string $method, string $prompt, array $options): string
    {
        return 'ai_provider:' . $this->getProviderName() . ':' . $method . ':' . md5($prompt . json_encode($options));
    }
}
