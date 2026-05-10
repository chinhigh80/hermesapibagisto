<?php

namespace App\Services\Hermes;

class AIParserService
{
    public function parse($prompt)
    {
        // Simple rule-based parsing for demonstration
        // In a real system, you'd use NLP or an LLM
        $prompt = strtolower(trim($prompt));

        // Example: "Create 10 Nike shoes priced at $50 with images and 100 stock each"
        if (preg_match('/create\s+(\d+)\s+(.+?)\s+priced\s+at\s+\$?(\d+(?:\.\d+)?)/i', $prompt, $matches)) {
            $count = intval($matches[1]);
            $productName = trim($matches[2]);
            $price = floatval($matches[3]);

            // Extract stock if mentioned
            $stock = 100; // default
            if (preg_match('/(\d+)\s+stock/i', $prompt, $stockMatches)) {
                $stock = intval($stockMatches[1]);
            }

            return [
                'action' => 'create_product',
                'data' => [
                    'name' => $productName,
                    'price' => $price,
                    'stock' => $stock,
                    'quantity' => $count,
                    'description' => "{$count} {$productName} items",
                    // In a real system, you'd fetch or generate images
                    'images' => [], // placeholder
                ]
            ];
        }

        // Bulk create
        if (preg_match('/create\s+(\d+)\s+(.+?)\s+products/i', $prompt, $matches)) {
            $count = intval($matches[1]);
            $productName = trim($matches[2]);

            return [
                'action' => 'bulk_create_products',
                'data' => [
                    'name' => $productName,
                    'price' => 0, // would need to be specified
                    'stock' => 0,
                    'quantity' => $count,
                ]
            ];
        }

        // Update store config
        if (preg_match('/set\s+store\s+name\s+to\s+(.+)/i', $prompt, $matches)) {
            return [
                'action' => 'update_store_config',
                'data' => [
                    'key' => 'shop.name',
                    'value' => trim($matches[1])
                ]
            ];
        }

        if (preg_match('/set\s+currency\s+to\s+(.+)/i', $prompt, $matches)) {
            return [
                'action' => 'update_store_config',
                'data' => [
                    'key' => 'currency',
                    'value' => trim($matches[1])
                ]
            ];
        }

        // Default fallback
        return [
            'action' => 'unknown',
            'data' => ['prompt' => $prompt]
        ];
    }
}