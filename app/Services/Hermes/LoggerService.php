<?php

namespace App\Services\Hermes;

use Illuminate\Support\Facades\Log;

class LoggerService
{
    public function log($level, $message = '', array $context = [])
    {
        Log::log($level, $message, $context);
    }
}