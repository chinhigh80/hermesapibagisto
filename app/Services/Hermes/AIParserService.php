<?php

namespace App\Services\Hermes;

use Exception;

class AIParserService
{
    /**
     * Parse a natural language prompt into a structured command.
     *
     * @param string $prompt
     * @return array ['action' => string, 'data' => array, 'confidence' => int]
     */
    public function parse($prompt)
    {
        $prompt = trim($prompt);
        if (empty($prompt)) {
            return ['action' => 'unknown', 'data' => [], 'confidence' => 0];
        }

        $promptLower = strtolower($prompt);

        // 1. Create product with details: "Create 10 Nike shoes priced at $50 with images and 100 stock each"
        if (preg_match('/create\s+(\d+)\s+(.+?)\s+priced\s+at\s+\$?(\d+(?:\.\d+)?)\s*(?:with\s+images\s+and\s+)?(\d+)\s+stock\s*each/i', $prompt, $matches)) {
            $count = intval($matches[1]);
            $name = trim($matches[2]);
            $price = floatval($matches[3]);
            $stock = intval($matches[4]);

            return [
                'action' => 'create_product',
                'data' => [
                    'name' => $name,
                    'price' => $price,
                    'stock' => $stock,
                    'quantity' => $count,
                    'description' => "{$count} {$name} items",
                    'images' => [], // placeholder; images would need to be provided separately or via another prompt
                ],
                'confidence' => 95,
            ];
        }

        // 2. Create product without stock: "Create 5 Apple watches priced at $200"
        if (preg_match('/create\s+(\d+)\s+(.+?)\s+priced\s+at\s+\$?(\d+(?:\.\d+)?)/i', $prompt, $matches)) {
            $count = intval($matches[1]);
            $name = trim($matches[2]);
            $price = floatval($matches[3]);

            return [
                'action' => 'create_product',
                'data' => [
                    'name' => $name,
                    'price' => $price,
                    'stock' => 0, // default stock 0; can be updated later via inventory
                    'quantity' => $count,
                    'description' => "{$count} {$name} items",
                    'images' => [],
                ],
                'confidence' => 90,
            ];
        }

        // 3. Bulk create products (simple): "Create 10 T-shirts products"
        if (preg_match('/create\s+(\d+)\s+(.+?)\s+products/i', $prompt, $matches)) {
            $count = intval($matches[1]);
            $name = trim($matches[2]);

            return [
                'action' => 'bulk_create_products',
                'data' => [
                    'name' => $name,
                    'price' => 0, // would need to be specified elsewhere; we'll set 0 and require update later
                    'stock' => 0,
                    'quantity' => $count,
                ],
                'confidence' => 85,
            ];
        }

        // 4. Update product: "Update product ID 5 price to $30"
        if (preg_match('/update\s+product\s+id\s+(\d+)\s+price\s+to\s+\$?(\d+(?:\.\d+)?)/i', $prompt, $matches)) {
            $productId = intval($matches[1]);
            $price = floatval($matches[2]);

            return [
                'action' => 'update_product',
                'data' => [
                    'product_id' => $productId,
                    'price' => $price,
                ],
                'confidence' => 90,
            ];
        }

        // 5. Update product stock: "Set stock of product ID 5 to 50"
        if (preg_match('/set\s+stock\s+of\s+product\s+id\s+(\d+)\s+to\s+(\d+)/i', $prompt, $matches)) {
            $productId = intval($matches[1]);
            $stock = intval($matches[2]);

            return [
                'action' => 'update_inventory',
                'data' => [
                    'product_id' => $productId,
                    'stock' => $stock,
                ],
                'confidence' => 90,
            ];
        }

        // 6. Upload images: "Upload images to product ID 5 from http://example.com/img1.jpg, http://example.com/img2.jpg"
        if (preg_match('/upload\s+images?\s+to\s+product\s+id\s+(\d+)\s+from\s+(.+)/i', $prompt, $matches)) {
            $productId = intval($matches[1]);
            $imagesStr = trim($matches[2]);
            // Split by commas and clean up
            $images = array_map('trim', explode(',', $imagesStr));
            $images = array_filter($images, 'strlen');

            return [
                'action' => 'upload_image',
                'data' => [
                    'product_id' => $productId,
                    'images' => $images,
                ],
                'confidence' => 85,
            ];
        }

        // 7. Update store config: "Set store name to 'My Shop'"
        if (preg_match('/set\s+store\s+name\s+to\s+["\']?(.+?)["\']?$/i', $prompt, $matches)) {
            $storeName = trim($matches[1], "\"' ");

            return [
                'action' => 'update_store_config',
                'data' => [
                    'store_name' => $storeName,
                ],
                'confidence' => 90,
            ];
        }

        // 8. Set currency: "Set currency to EUR"
        if (preg_match('/set\s+currency\s+to\s+([A-Z]{3})/i', $prompt, $matches)) {
            $currency = strtoupper($matches[1]);

            return [
                'action' => 'update_store_config',
                'data' => [
                    'currency' => $currency,
                ],
                'confidence' => 90,
            ];
        }

        // 9. Set locale: "Set locale to fr_FR"
        if (preg_match('/set\s+locale\s+to\s+([a-z]{2}(?:_[A-Z]{2})?)/i', $prompt, $matches)) {
            $locale = $matches[1];

            return [
                'action' => 'update_store_config',
                'data' => [
                    'locale' => $locale,
                ],
                'confidence' => 90,
            ];
        }

        // 10. Set timezone: "Set timezone to Asia/Kolkata"
        if (preg_match('/set\s+timezone\s+to\s+(.+)/i', $prompt, $matches)) {
            $timezone = trim($matches[1]);

            return [
                'action' => 'update_store_config',
                'data' => [
                    'timezone' => $timezone,
                ],
                'confidence' => 90,
            ];
        }

        // 11. Manage orders: create order (simplified)
        // Example: "Create order for customer@example.com with 2 x product ID 5 at $10 each"
        if (preg_match('/create\s+order\s+for\s+([^\s]+)\s+with\s+(\d+)\s+x\s+product\s+id\s+(\d+)\s+at\s+\$?(\d+(?:\.\d+)?)\s*each/i', $prompt, $matches)) {
            $customerEmail = $matches[1];
            $quantity = intval($matches[2]);
            $productId = intval($matches[3]);
            $price = floatval($matches[4]);

            return [
                'action' => 'manage_orders',
                'data' => [
                    'action' => 'create',
                    'customer_email' => $customerEmail,
                    'items' => [
                        [
                            'product_id' => $productId,
                            'quantity' => $quantity,
                            'price' => $price,
                        ],
                    ],
                ],
                'confidence' => 80,
            ];
        }

        // 12. Update order status: "Set order ID 10 status to processing"
        if (preg_match('/set\s+order\s+id\s+(\d+)\s+status\s+to\s+(\w+)/i', $prompt, $matches)) {
            $orderId = intval($matches[1]);
            $status = strtolower($matches[2]);

            return [
                'action' => 'manage_orders',
                'data' => [
                    'action' => 'update_status',
                    'order_id' => $orderId,
                    'status' => $status,
                ],
                'confidence' => 85,
            ];
        }

        // 13. Cancel order: "Cancel order ID 10"
        if (preg_match('/cancel\s+order\s+id\s+(\d+)/i', $prompt, $matches)) {
            $orderId = intval($matches[1]);

            return [
                'action' => 'manage_orders',
                'data' => [
                    'action' => 'cancel',
                    'order_id' => $orderId,
                ],
                'confidence' => 85,
            ];
        }

        // If none matched, return unknown
        return [
            'action' => 'unknown',
            'data' => ['prompt' => $prompt],
            'confidence' => 0,
        ];
    }
}