<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Hermes\AIParserService;
use App\Services\Hermes\PolicyEngineService;
use App\Services\Hermes\ProductService;
use App\Services\Hermes\OrderService;
use App\Services\Hermes\ConfigService;
use App\Services\Hermes\ImageService;
use App\Services\Hermes\LoggerService;
use Exception;

class HermesController extends Controller
{
    protected $aiParser;
    protected $policyEngine;
    protected $productService;
    protected $orderService;
    protected $configService;
    protected $imageService;
    protected $logger;

    public function __construct(
        AIParserService $aiParser,
        PolicyEngineService $policyEngine,
        ProductService $productService,
        OrderService $orderService,
        ConfigService $configService,
        ImageService $imageService,
        LoggerService $logger
    ) {
        $this->aiParser = $aiParser;
        $this->policyEngine = $policyEngine;
        $this->productService = $productService;
        $this->orderService = $orderService;
        $this->configService = $configService;
        $this->imageService = $imageService;
        $this->logger = $logger;
    }

    public function processCommand(Request $request)
    {
        try {
            $prompt = $request->input('prompt');
            $apiKey = $request->header('X-API-KEY');

            // Validate API key (simple example, in production use a proper auth system)
            if (!$this->validateApiKey($apiKey)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Parse the prompt into structured JSON
            $parsed = $this->aiParser->parse($prompt);

            // Validate the action against policies
            if (!$this->policyEngine->validate($parsed)) {
                return response()->json(['error' => 'Action not allowed by policy'], 403);
            }

            // Log the action
            $this->logger->log('command_processed', [
                'prompt' => $prompt,
                'parsed' => $parsed,
                'api_key' => $apiKey
            ]);

            // Route to appropriate service based on action type
            switch ($parsed['action']) {
                case 'create_product':
                    $result = $this->productService->create($parsed['data']);
                    break;
                case 'bulk_create_products':
                    $result = $this->productService->bulkCreate($parsed['data']);
                    break;
                case 'upload_image':
                    $result = $this->imageService->upload($parsed['data']);
                    break;
                case 'update_store_config':
                    $result = $this->configService->update($parsed['data']);
                    break;
                case 'manage_orders':
                    $result = $this->orderService->manage($parsed['data']);
                    break;
                case 'update_inventory':
                    $result = $this->orderService->updateInventory($parsed['data']); // Note: using orderService for inventory? Might be better to have InventoryService, but for now we'll use orderService or create a new one.
                    break;
                default:
                    throw new Exception('Unknown action: ' . $parsed['action']);
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            $this->logger->log('error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Simple API key validation (replace with your own logic)
    protected function validateApiKey($key)
    {
        // For now, we'll accept any non-empty key. In production, check against a database or env.
        return !empty($key);
    }

    // The following methods are for individual endpoints (if needed)
    public function createProduct(Request $request)
    {
        // Similar to above but for a specific endpoint
        // We'll reuse the same logic but without the AI parser for direct calls
        // For brevity, we'll just call the service directly.
        try {
            $apiKey = $request->header('X-API-KEY');
            if (!$this->validateApiKey($apiKey)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $result = $this->productService->create($request->all());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function bulkCreateProducts(Request $request)
    {
        try {
            $apiKey = $request->header('X-API-KEY');
            if (!$this->validateApiKey($apiKey)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $result = $this->productService->bulkCreate($request->all());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function uploadImage(Request $request)
    {
        try {
            $apiKey = $request->header('X-API-KEY');
            if (!$this->validateApiKey($apiKey)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $result = $this->imageService->upload($request->all());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateStoreConfig(Request $request)
    {
        try {
            $apiKey = $request->header('X-API-KEY');
            if (!$this->validateApiKey($apiKey)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $result = $this->configService->update($request->all());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function manageOrders(Request $request)
    {
        try {
            $apiKey = $request->header('X-API-KEY');
            if (!$this->validateApiKey($apiKey)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $result = $this->orderService->manage($request->all());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateInventory(Request $request)
    {
        try {
            $apiKey = $request->header('X-API-KEY');
            if (!$this->validateApiKey($apiKey)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $result = $this->orderService->updateInventory($request->all());
            return response()->json(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}