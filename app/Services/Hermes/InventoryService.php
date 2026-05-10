<?php

namespace App\Services\Hermes;

use Webkul\Product\Repositories\ProductRepository;
use Webkul\Inventory\Repositories\StockRepository;
use Webkul\Product\Repositories\ProductInventoryRepository;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    protected $productRepository;
    protected $stockRepository;
    protected $productInventoryRepository;

    public function __construct(
        ProductRepository $productRepository,
        StockRepository $stockRepository,
        ProductInventoryRepository $productInventoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->stockRepository = $stockRepository;
        $this->productInventoryRepository = $productInventoryRepository;
    }

    /**
     * Update inventory/stock for a product.
     *
     * Expected $data:
     *   [
     *       'product_id' => int,
     *       'stock' => int, // new quantity
     *       'stock_code' => string|null // optional, defaults to default stock
     *   ]
     *
     * @param array $data
     * @return array
     */
    public function update(array $data)
    {
        if (!isset($data['product_id']) || !isset($data['stock'])) {
            throw new Exception('Product ID and stock quantity are required');
        }

        $quantity = intval($data['stock']);
        if ($quantity < 0) {
            throw new Exception('Stock quantity cannot be negative');
        }

        return DB::transaction(function () use ($data, $quantity) {
            $product = $this->productRepository->find($data['product_id']);
            if (!$product) {
                throw new Exception("Product not found: {$data['product_id']}");
            }

            $stockCode = $data['stock_code'] ?? null;
            $stock = null;
            if ($stockCode) {
                $stock = $this->stockRepository->findOneBy(['code' => $stockCode]);
            }
            if (!$stock) {
                // Get default stock
                $stock = $this->stockRepository->findOneBy(['code' => BagistoConstants::DEFAULT_STOCK]);
            }
            if (!$stock) {
                throw new Exception('Stock not found');
            }

            // Check if inventory record exists for this product and stock
            $inventory = $this->productInventoryRepository->findOneBy([
                'product_id' => $product->id,
                'stock_id'   => $stock->id,
            ]);

            if ($inventory) {
                // Update existing
                $inventory->update(['quantity' => $quantity]);
            } else {
                // Create new
                $this->productInventoryRepository->create([
                    'product_id' => $product->id,
                    'stock_id'   => $stock->id,
                    'quantity'   => $quantity,
                ]);
            }

            // Update product's saleable flag based on stock
            $saleable = $quantity > 0 ? 1 : 0;
            $product->update(['saleable' => $saleable]);

            return $inventory ?? $this->productInventoryRepository->findOneBy([
                'product_id' => $product->id,
                'stock_id'   => $stock->id,
            ]);
        });
    }
}

// Helper class for constants (same as in ProductService)
class BagistoConstants
{
    const DEFAULT_CHANNEL = 'default';
    const DEFAULT_LOCALE = 'en';
    const DEFAULT_STOCK = 'default';
}