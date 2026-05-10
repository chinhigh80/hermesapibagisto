<?php

namespace App\Services\Hermes;

use Webkul\Product\Repositories\ProductRepository;
use Webkul\Product\Repositories\ProductAttributeOptionRepository;
use Webkul\Channel\Repositories\ChannelRepository;
use Webkul\Locale\Repositories\LocaleRepository;
use Webkul\Inventory\Repositories\StockRepository;
use Webkul\Product\Repositories\ProductInventoryRepository;
use Webkul\MediaGallery\Repositories\MediaGalleryRepository;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductService
{
    protected $productRepository;
    protected $channelRepository;
    protected $localeRepository;
    protected $stockRepository;
    protected $productInventoryRepository;
    protected $mediaGalleryRepository;
    protected $attributeOptionRepository;

    public function __construct(
        ProductRepository $productRepository,
        ChannelRepository $channelRepository,
        LocaleRepository $localeRepository,
        StockRepository $stockRepository,
        ProductInventoryRepository $productInventoryRepository,
        MediaGalleryRepository $mediaGalleryRepository,
        ProductAttributeOptionRepository $attributeOptionRepository
    ) {
        $this->productRepository = $productRepository;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
        $this->stockRepository = $stockRepository;
        $this->productInventoryRepository = $productInventoryRepository;
        $this->mediaGalleryRepository = $mediaGalleryRepository;
        $this->attributeOptionRepository = $attributeOptionRepository;
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Get default channel and locale
            $channel = $this->channelRepository->findOneBy(['code' => BagistoConstants::DEFAULT_CHANNEL]);
            $locale = $this->localeRepository->findOneBy(['code' => BagistoConstants::DEFAULT_LOCALE]);

            if (!$channel || !$locale) {
                throw new Exception('Default channel or locale not found');
            }

            // Prepare product data
            $productData = [
                'name' => [
                    $locale->code => $data['name'],
                ],
                'description' => [
                    $locale->code => isset($data['description']) ? $data['description'] : '',
                ],
                'short_description' => [
                    $locale->code => isset($data['short_description']) ? $data['short_description'] : '',
                ],
                'url_key' => [
                    $locale->code => strtolower(str_replace(' ', '-', $data['name'])),
                ],
                'type' => 'simple',
                'attribute_family' => 'default',
                'price' => $data['price'],
                'cost' => isset($data['cost']) ? $data['cost'] : 0,
                'weight' => isset($data['weight']) ? $data['weight'] : 0,
                'height' => isset($data['height']) ? $data['height'] : 0,
                'width' => isset($data['width']) ? $data['width'] : 0,
                'depth' => isset($data['depth']) ? $data['depth'] : 0,
                'status' => 1,
                'visible_individually' => 1,
                'meta_title' => [
                    $locale->code => $data['name'],
                ],
                'meta_description' => [
                    $locale->code => isset($data['meta_description']) ? $data['meta_description'] : '',
                ],
                'meta_keywords' => [
                    $locale->code => isset($data['meta_keywords']) ? $data['meta_keywords'] : '',
                ],
                'new_from_date' => isset($data['new_from_date']) ? $data['new_from_date'] : null,
                'new_to_date' => isset($data['new_to_date']) ? $data['new_to_date'] : null,
                'saleable' => $data['stock'] > 0 ? 1 : 0,
            ];

            // Create product
            $product = $this->productRepository->create($productData);

            // Assign to channel
            $product->channels()->attach($channel->id);

            // Manage inventory
            if (isset($data['stock'])) {
                // Get default stock
                $stock = $this->stockRepository->findOneBy(['code' => BagistoConstants::DEFAULT_STOCK]);
                if ($stock) {
                    $this->productInventoryRepository->create([
                        'product_id' => $product->id,
                        'stock_id'   => $stock->id,
                        'quantity'   => $data['stock'],
                    ]);
                }
            }

            // Handle images if provided
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $imageUrl) {
                    // Download image from URL and add to media gallery
                    // For simplicity, we'll assume the image is already uploaded and we have a path.
                    // In a real system, you'd download and store it.
                    // We'll skip the actual download for now and just note that we need to implement ImageService.
                    // We'll call ImageService to handle this.
                    // But to avoid circular dependency, we'll leave it to the controller to handle images before calling this.
                    // So we'll assume images are already handled.
                }
            }

            return $product;
        });
    }

    public function bulkCreate(array $data)
    {
        $products = [];
        $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;

        for ($i = 0; $i < $quantity; $i++) {
            $productData = [
                'name' => $data['name'] . ' ' . ($i + 1),
                'price' => $data['price'],
                'stock' => $data['stock'],
                'description' => $data['description'] ?? '',
            ];
            $products[] = $this->create($productData);
        }

        return $products;
    }

    /**
     * Update an existing product.
     *
     * Expected $data:
     *   [
     *       'product_id' => int,
     *       'name' => string|null,
     *       'price' => float|null,
     *       'stock' => int|null,
     *       'description' => string|null,
     *       ... other fields
     *   ]
     *
     * @param array $data
     * @return mixed
     */
    public function update(array $data)
    {
        return DB::transaction(function () use ($data) {
            if (!isset($data['product_id'])) {
                throw new Exception('Product ID is required for update');
            }

            $product = $this->productRepository->find($data['product_id']);
            if (!$product) {
                throw new Exception("Product not found: {$data['product_id']}");
            }

            // Get default locale for translations
            $locale = $this->localeRepository->findOneBy(['code' => BagistoConstants::DEFAULT_LOCALE]);
            if (!$locale) {
                throw new Exception('Default locale not found');
            }

            // Prepare update data
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'][$locale->code] = $data['name'];
                $updateData['meta_title'][$locale->code] = $data['name'];
            }
            if (isset($data['description'])) {
                $updateData['description'][$locale->code] = $data['description'];
            }
            if (isset($data['short_description'])) {
                $updateData['short_description'][$locale->code] = $data['short_description'];
            }
            if (isset($data['price'])) {
                $updateData['price'] = $data['price'];
            }
            if (isset($data['cost'])) {
                $updateData['cost'] = $data['cost'];
            }
            if (isset($data['weight'])) {
                $updateData['weight'] = $data['weight'];
            }
            if (isset($data['height'])) {
                $updateData['height'] = $data['height'];
            }
            if (isset($data['width'])) {
                $updateData['width'] = $data['width'];
            }
            if (isset($data['depth'])) {
                $updateData['depth'] = $data['depth'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if (isset($data['visible_individually'])) {
                $updateData['visible_individually'] = $data['visible_individually'];
            }
            if (isset($data['meta_description'])) {
                $updateData['meta_description'][$locale->code] = $data['meta_description'];
            }
            if (isset($data['meta_keywords'])) {
                $updateData['meta_keywords'][$locale->code] = $data['meta_keywords'];
            }
            if (isset($data['new_from_date'])) {
                $updateData['new_from_date'] = $data['new_from_date'];
            }
            if (isset($data['new_to_date'])) {
                $updateData['new_to_date'] = $data['new_to_date'];
            }

            // Update product
            $product->update($updateData);

            // Update inventory if stock is provided
            if (isset($data['stock'])) {
                $stock = $this->stockRepository->findOneBy(['code' => BagistoConstants::DEFAULT_STOCK]);
                if ($stock) {
                    $inventory = $this->productInventoryRepository->findOneBy([
                        'product_id' => $product->id,
                        'stock_id'   => $stock->id,
                    ]);
                    if ($inventory) {
                        $inventory->update(['quantity' => $data['stock']]);
                    } else {
                        $this->productInventoryRepository->create([
                            'product_id' => $product->id,
                            'stock_id'   => $stock->id,
                            'quantity'   => $data['stock'],
                        ]);
                    }
                    // Update saleable flag
                    $product->update(['saleable' => $data['stock'] > 0 ? 1 : 0]);
                }
            }

            // Handle images if provided (we'll assume images are handled by ImageService before calling this)
            // If we want to handle images here, we would need to download and attach them.
            // For now, we leave it to the controller to use ImageService separately.

            return $product;
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