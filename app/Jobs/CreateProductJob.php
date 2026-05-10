<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Hermes\ProductService;
use App\Services\Hermes\ImageService;
use App\Services\Hermes\InventoryService;
use Illuminate\Support\Facades\Log;

class CreateProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Product data.
     *
     * @var array
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param array $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @param ProductService $productService
     * @param ImageService $imageService
     * @param InventoryService $inventoryService
     * @return void
     */
    public function handle(ProductService $productService, ImageService $imageService, InventoryService $inventoryService)
    {
        try {
            Log::info('CreateProductJob started with data: ' . json_encode($this->data));

            // Extract data
            $name = $this->data['name'] ?? '';
            $price = $this->data['price'] ?? 0;
            $stock = $this->data['stock'] ?? 0;
            $description = $this->data['description'] ?? '';
            $images = $this->data['images'] ?? [];

            // Create product via ProductService
            $product = $productService->createProduct([
                'name' => $name,
                'price' => $price,
                'description' => $description,
                // other required fields like channel_id, locale_id, etc. would be fetched by the service
            ]);

            if (!$product) {
                throw new \Exception('Product creation returned null or false.');
            }

            // If stock is provided, update inventory
            if ($stock > 0) {
                $inventoryService->updateStock($product->id, $stock);
            }

            // If images are provided, download and attach them
            if (!empty($images)) {
                $imageService->uploadImagesFromUrls($product->id, $images);
            }

            Log::info('CreateProductJob completed successfully for product ID: ' . $product->id);
        } catch (\Exception $e) {
            Log::error('CreateProductJob failed: ' . $e->getMessage());
            // The job will be retried based on the configuration in the queue.
            throw $e;
        }
    }
}
