<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface;

class NVIDIAProvider implements AIProviderInterface
{
    protected string $apiKey;
    protected string $model;
    protected int $timeout;
    protected int $maxTokens;
    protected string $baseUrl = 'https://ai.api.nvidia.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.nvidia.api_key');
        $this->model = config('services.nvidia.model', 'nemotron-3-super-120b-a12b');
        $this->timeout = config('services.nvidia.timeout', 120);
        $this->maxTokens = config('services.nvidia.max_tokens', 4096);
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
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->withTimeout($this->timeout)
            ->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
                'stream' => false,
            ]);

            if ($response->successful()) {
                $result = $response->json()['choices'][0]['message']['content'] ?? '';
                Cache::put($cacheKey, $result, now()->addMinutes(10));
                return $result;
            }

            Log::error('NVIDIA API error: ' . $response->body());
            throw new \RuntimeException('NVIDIA API request failed: ' . $response->status());
        } catch (\Exception $e) {
            Log::error('NVIDIA Provider error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function completeJson(string $prompt, array $options = []): ?array
    {
        // Add instruction to return JSON
        $jsonPrompt = $prompt . "\n\nReturn ONLY valid JSON, no additional text.";
        $response = $this->complete($jsonPrompt, $options);

        // Try to parse JSON
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // If not valid JSON, try to extract JSON from the response
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        Log::warning('NVIDIA Provider: Failed to parse JSON from response: ' . substr($response, 0, 200));
        return null;
    }

    public function getProviderName(): string
    {
        return 'nvidia';
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
