<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class AIManagerService
{
    /**
     * @var AIProviderInterface[]
     */
    protected array $providers = [];

    /**
     * @var string
     */
    protected string $primaryProvider;

    /**
     * @var string
     */
    protected string $fallbackProvider;

    /**
     * @var int
     */
    protected int $maxRetries = 3;

    /**
     * @var int
     */
    protected int $retryDelay = 1000; // milliseconds

    public function __construct()
    {
        $this->initializeProviders();
        $this->primaryProvider = config('services.ai.provider', 'nvidia');
        $this->fallbackProvider = config('services.ai.fallback_provider', 'openai');
    }

    protected function initializeProviders(): void
    {
        $this->providers['nvidia'] = new NVIDIAProvider();
        $this->providers['openai'] = new OpenAIProvider();
        $this->providers['claude'] = new ClaudeProvider();
        $this->providers['local_llm'] = new LocalLLMProvider();

        // Filter out unavailable providers
        $this->providers = collect($this->providers)
            ->filter(fn($provider) => $provider->isAvailable())
            ->toArray();
    }

    /**
     * Get a provider by name.
     *
     * @param string $name
     * @return AIProviderInterface|null
     */
    public function getProvider(string $name): ?AIProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get the primary provider if available, otherwise fallback.
     *
     * @return AIProviderInterface
     */
    public function getPrimaryProvider(): AIProviderInterface
    {
        $provider = $this->getProvider($this->primaryProvider);
        if ($provider) {
            return $provider;
        }

        // Fallback to the configured fallback provider
        $fallback = $this->getProvider($this->fallbackProvider);
        if ($fallback) {
            return $fallback;
        }

        // Last resort: any available provider
        $first = reset($this->providers);
        if ($first) {
            return $first;
        }

        throw new \RuntimeException('No AI providers available');
    }

    /**
     * Execute a completion with retry logic and fallback.
     *
     * @param string $prompt
     * @param array  $options
     * @param bool   $useJson Whether to use JSON mode
     * @return string|array|null
     */
    public function execute(string $prompt, array $options = [], bool $useJson = false)
    {
        $lastException = null;

        // Try primary provider with retries
        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                $provider = $this->getPrimaryProvider();
                if ($useJson) {
                    return $provider->completeJson($prompt, $options);
                } else {
                    return $provider->complete($prompt, $options);
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('AI provider ' . $this->primaryProvider . ' attempt ' . ($attempt + 1) . ' failed: ' . $e->getMessage());

                // If we are not on the last attempt, wait before retrying
                if ($attempt < $this->maxRetries - 1) {
                    usleep($this->retryDelay * 1000); // convert ms to microseconds
                }
            }
        }

        // If primary provider failed after retries, try fallback provider
        if ($this->primaryProvider !== $this->fallbackProvider) {
            try {
                $provider = $this->getProvider($this->fallbackProvider);
                if ($provider) {
                    if ($useJson) {
                        return $provider->completeJson($prompt, $options);
                    } else {
                        return $provider->complete($prompt, $options);
                    }
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("AI fallback provider {$this->fallbackProvider} failed: {$e->getMessage()}");
            }
        }

        // If we still have providers left, try any other available provider
        foreach ($this->providers as $name => $provider) {
            if ($name === $this->primaryProvider || $name === $this->fallbackProvider) {
                continue;
            }
            try {
                if ($useJson) {
                    return $provider->completeJson($prompt, $options);
                } else {
                    return $provider->complete($prompt, $options);
                }
            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("AI provider {$name} failed: {$e->getMessage()}");
            }
        }

        // If all providers fail, throw the last exception
        throw new \RuntimeException('All AI providers failed: ' . ($lastException->getMessage() ?? 'Unknown error'), 0, $lastException);
    }

    /**
     * Get token usage statistics (if available from providers).
     * This is a placeholder - in a real implementation, each provider would return usage.
     *
     * @return array
     */
    public function getTokenUsage(): array
    {
        // This would be implemented by tracking usage from each provider.
        // For now, we return empty array.
        return [];
    }
}
