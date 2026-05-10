<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClaudeProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected int $maxTokens;
    protected string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model', 'claude-3-opus-20240229');
        $this->timeout = config('services.claude.timeout', 120);
        $this->maxTokens = config('services.claude.max_tokens', 4096);
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
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->withTimeout($this->timeout)
            ->post($this->baseUrl . '/messages', [
                'model' => $this->model,
                'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
                'temperature' => $options['temperature'] ?? 0.7,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->successful()) {
                $result = $response->json()['content'][0]['text'] ?? '';
                Cache::put($cacheKey, $result, now()->addMinutes(10));
                return $result;
            }

            Log::error('Claude API error: ' . $response->body());
            throw new \RuntimeException('Claude API request failed: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('Claude Provider error: ' . $e->getMessage());
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

        Log::warning('Claude Provider: Failed to parse JSON from response: ' . substr($response, 0, 200));
        return null;
    }

    public function getProviderName(): string
    {
        return 'claude';
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
