<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default AI provider to use for the AI Manager.
    |
    */

    'provider' => env('AI_PROVIDER', 'nvidia'),

    /*
    |--------------------------------------------------------------------------
    | Fallback AI Provider
    |--------------------------------------------------------------------------
    |
    | The fallback AI provider to use if the primary provider fails.
    |
    */

    'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | NVIDIA AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for NVIDIA AI/NIM inference.
    |
    */

    'nvidia' => [

        'api_key' => env('NVIDIA_API_KEY', ''),

        'model' => env('AI_MODEL', 'nemotron-3-super-120b-a12b'),

        'timeout' => env('AI_TIMEOUT', 120),

        'max_tokens' => env('AI_MAX_TOKENS', 4096),

    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    */

    'openai' => [

        'api_key' => env('OPENAI_API_KEY', ''),

        'model' => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),

        'timeout' => env('OPENAI_TIMEOUT', 120),

        'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),

    ],

    /*
    |--------------------------------------------------------------------------
    | Claude Configuration
    |--------------------------------------------------------------------------
    */

    'claude' => [

        'api_key' => env('CLAUDE_API_KEY', ''),

        'model' => env('CLAUDE_MODEL', 'claude-3-opus-20240229'),

        'timeout' => env('CLAUDE_TIMEOUT', 120),

        'max_tokens' => env('CLAUDE_MAX_TOKENS', 4096),

    ],

    /*
    |--------------------------------------------------------------------------
    | Local LLM Configuration
    |--------------------------------------------------------------------------
    */

    'local_llm' => [

        'api_url' => env('LOCAL_LLM_API_URL', 'http://localhost:8080/v1'),

        'model' => env('LOCAL_LLM_MODEL', 'local-model'),

        'timeout' => env('LOCAL_LLM_TIMEOUT', 120),

        'max_tokens' => env('LOCAL_LLM_MAX_TOKENS', 4096),

    ],

];
