<?php

namespace App\Services\Hermes;

use Webkul\MediaGallery\Repositories\MediaGalleryRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Exception;

class ImageService
{
    protected $mediaGallery;

    public function __construct(MediaGalleryRepository $mediaGallery)
    {
        $this->mediaGallery = $mediaGallery;
    }

    /**
     * Upload images from URLs and attach to a product.
     *
     * @param array $data ['product_id' => int, 'images' => ['url1', 'url2', ...]]
     * @return array
     */
    public function upload(array $data)
    {
        if (!isset($data['product_id']) || !isset($data['images']) || !is_array($data['images'])) {
            throw new Exception('Invalid data: product_id and images array required');
        }

        $productId = $data['product_id'];
        $uploaded = [];

        foreach ($data['images'] as $url) {
            try {
                // Download image
                $response = Http::get($url);
                if (!$response->successful()) {
                    throw new Exception("Failed to download image from {$url}");
                }

                $contents = $response->body();

                // Validate image (basic mime check)
                $mime = $response->header('Content-Type');
                if (!preg_match('/^image\/(jpeg|png|gif|webp)$/', $mime ?? '')) {
                    // fallback to file extension check
                    $ext = Str::lower(pathinfo($url, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        throw new Exception("Invalid image type for {$url}");
                    }
                }

                // Generate a unique filename
                $filename = Str::uuid() . '.' . ($ext ?? 'jpg');

                // Store in public disk (or you can use s3 etc.)
                $path = Storage::disk('public')->putFileAs('hermes-images', $contents, $filename);

                // Get the URL for the stored image
                $url = Storage::disk('public')->url($path);

                // Add to media gallery
                $media = $this->mediaGallery->create([
                    'file_name' => $filename,
                    'mime_type' => $mime ?? 'image/jpeg',
                    'disk' => 'public',
                    'destination_path' => 'hermes-images',
                    'size' => strlen($contents),
                ]);

                // Associate media with product (Bagisto uses product_media_galleries pivot?)
                // Assuming there is a relation; we'll attach via product's media gallery.
                // We need to find the product and attach media.
                // For simplicity, we'll assume there's a method to attach media to product.
                // We'll need to use ProductRepository to find product and then attach.
                // However, we don't have product repo here; we could pass it via DI or use service container.
                // Let's do it via app binding.

                // We'll resolve ProductRepository from container.
                $productRepository = app(\Webkul\Product\Repositories\ProductRepository::class);
                $product = $productRepository->find($productId);
                if (!$product) {
                    throw new Exception("Product not found: {$productId}");
                }

                // Attach media to product (Bagisto's product model may have a medias relation)
                // We'll use the media gallery's attach method if exists.
                // If not, we can create a product_media_gallery record.
                // We'll use DB to insert into product_media_galleries table.
                \DB::table('product_media_galleries')->insert([
                    'product_id' => $product->id,
                    'media_gallery_id' => $media->id,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $uploaded[] = [
                    'url' => $url,
                    'media_id' => $media->id,
                ];
            } catch (Exception $e) {
                // Log error and continue?
                \Log::error("ImageService error: {$e->getMessage()}");
                // Optionally throw to stop whole operation
                throw $e;
            }
        }

        return $uploaded;
    }
}