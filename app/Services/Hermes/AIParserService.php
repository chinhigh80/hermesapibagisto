<?php

namespace App\Services\Hermes;

use App\Services\AI\AIManagerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AIParserService
{
    /**
     * @var AIManagerService
     */
    protected $aiManager;

    /**
     * Constructor.
     */
    public function __construct(AIManagerService $aiManager)
    {
        $this->aiManager = $aiManager;
    }

    /**
     * Parse a natural language command into a structured action.
     *
     * @param string $prompt The natural language command.
     * @return array Structured command with action, data, confidence, reasoning, and follow_up_questions.
     */
    public function parse(string $prompt): array
    {
        // Construct the system prompt for the LLM to extract the command structure.
        $systemPrompt = <<<EOT
You are an AI assistant for an ecommerce operating system. Your task is to parse the user's natural language command into a structured JSON action.

You must return a JSON object with the following fields:
- action: string (the action to perform, e.g., "create_product", "bulk_create_products", "update_product", "update_inventory", "manage_orders", "upload_image", "update_store_config")
- data: object (the data required for the action, structure depends on the action)
- confidence: integer (0-100, how confident you are in the parsing)
- reasoning: string (brief explanation of how you arrived at the action and data)
- follow_up_questions: array of strings (any clarifying questions needed if the prompt is ambiguous)

### Action Definitions:

1. **create_product**: Create a single product.
   Data: { name: string, price: number (>=0), stock: integer (>=0), description?: string, images?: array of strings (URLs) }

2. **bulk_create_products**: Create multiple products of the same type.
   Data: { name: string, price: number (>=0), stock: integer (>=0), quantity: integer (1-50), description?: string, images?: array of strings (URLs) }

3. **update_product**: Update an existing product.
   Data: { product_id: integer, price?: number, stock?: integer, name?: string, description?: string }

4. **update_inventory**: Update stock for a product.
   Data: { product_id: integer, stock: integer (>=0) }

5. **manage_orders**: Manage orders (create, update status, cancel).
   Data: { 
        action: string (one of: "create", "update_status", "cancel"),
        // For create:
        customer_email?: string, 
        customer_name?: string,
        items: array of { product_id: integer, quantity: integer, price: number },
        // For update_status:
        order_id: integer, 
        status: string (must be a valid Bagisto order status),
        // For cancel:
        order_id: integer
   }

6. **upload_image**: Upload images to a product.
   Data: { product_id: integer, images: array of strings (URLs) }

7. **update_store_config**: Update store settings.
   Data: { store_name?: string, currency?: string, locale?: string, timezone?: string, tax_settings?: array }

### Rules:
- If the user mentions a price, it must be a number >= 0.
- Stock must be an integer >= 0.
- For bulk operations, quantity must be between 1 and 50.
- If the user does not provide enough information, set confidence lower and include follow_up_questions.
- If the prompt is unclear or could be multiple actions, choose the most likely and set confidence accordingly.
- Always try to extract as much data as possible from the prompt.
- If the user mentions "previous product" or similar, you cannot know the ID, so you should ask for clarification (follow_up_questions).
- Do not make up data that is not in the prompt.

### Examples:

Prompt: "Create 10 Nike shoes priced at $80 with images and 200 stock each"
Output:
{
  "action": "bulk_create_products",
  "data": {
    "name": "Nike shoes",
    "price": 80,
    "stock": 200,
    "quantity": 10,
    "description": "10 Nike shoes items",
    "images": []
  },
  "confidence": 95,
  "reasoning": "The user wants to create 10 of the same product with given price and stock.",
  "follow_up_questions": []
}

Prompt: "Set store name to \"My Shop\""
Output:
{
  "action": "update_store_config",
  "data": {
    "store_name": "My Shop"
  },
  "confidence": 90,
  "reasoning": "The user wants to update the store name.",
  "follow_up_questions": []
}

Prompt: "Upload images to product ID 12 from https://ex.com/i1.jpg, https://ex.com/i2.jpg"
Output:
{
  "action": "upload_image",
  "data": {
    "product_id": 12,
    "images": ["https://ex.com/i1.jpg", "https://ex.com/i2.jpg"]
  },
  "confidence": 95,
  "reasoning": "The user wants to upload two images to product 12.",
  "follow_up_questions": []
}

Prompt: "Create luxury variants of the previous product"
Output:
{
  "action": "create_product",
  "data": {
    "name": "luxury variant",
    "price": 0, // placeholder, will be clarified
    "stock": 0, // placeholder
    "description": "Luxury variant of the previous product"
  },
  "confidence": 60,
  "reasoning": "The user wants to create a luxury variant, but we don't know the previous product's details.",
  "follow_up_questions": [
    "What is the name or ID of the previous product?",
    "What price and stock should the luxury variant have?"
  ]
}

Now, parse the following user prompt and return ONLY the JSON object (no additional text):
EOT;

        $fullPrompt = $systemPrompt . "\n\nUser prompt: " . $prompt;

        try {
            // Use the AI manager to get a JSON response.
            $result = $this->aiManager->execute($fullPrompt, [], true); // true for JSON mode

            if ($result && is_array($result)) {
                // Ensure all required fields are present, set defaults if missing.
                $result['action'] = $result['action'] ?? '';
                $result['data'] = $result['data'] ?? [];
                $result['confidence'] = $result['confidence'] ?? 0;
                $result['reasoning'] = $result['reasoning'] ?? '';
                $result['follow_up_questions'] = $result['follow_up_questions'] ?? [];

                // Ensure confidence is integer between 0 and 100.
                $result['confidence'] = max(0, min(100, intval($result['confidence'])));

                // Log the parsing result for debugging.
                Log::debug('AI Parser result: ' . json_encode($result));

                return $result;
            }

            // If the AI didn't return a valid array, fallback to a safe default.
            Log::warning('AI Parser did not return a valid array, falling back to safe default.');
            return $this->getFallbackParse($prompt);
        } catch (\Exception $e) {
            Log::error('AI Parser error: ' . $e->getMessage());
            return $this->getFallbackParse($prompt);
        }
    }

    /**
     * Fallback parser in case AI fails.
     * This is a very basic regex-based parser for emergency use only.
     * We keep it simple to avoid breaking the system.
     *
     * @param string $prompt
     * @return array
     */
    protected function getFallbackParse(string $prompt): array
    {
        $promptLower = strtolower($prompt);

        // Very basic fallback: if it looks like a create product command.
        if (preg_match('/create.*(\d+)\s*.*(\d+(?:\.\d+)?)\s*dollar/i', $promptLower, $matches)) {
            $quantity = intval($matches[1]) ?? 1;
            $price = floatval($matches[2]) ?? 0;
            return [
                'action' => 'bulk_create_products',
                'data' => [
                    'name' => 'Product',
                    'price' => $price,
                    'stock' => 0,
                    'quantity' => min($quantity, 50),
                ],
                'confidence' => 30,
                'reasoning' => 'Fallback parser: detected create command with quantity and price.',
                'follow_up_questions' => ['Please provide more details for the product.']
            ];
        }

        // Default fallback.
        return [
            'action' => '',
            'data' => [],
            'confidence' => 0,
            'reasoning' => 'Fallback parser: could not parse the command.',
            'follow_up_questions' => ['Please rephrase your command.']
        ];
    }
}
