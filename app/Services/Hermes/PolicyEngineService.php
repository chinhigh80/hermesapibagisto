<?php

namespace App\Services\Hermes;

use Exception;

class PolicyEngineService
{
    /**
     * Validate an action against policies.
     *
     * @param array $parsed ['action' => string, 'data' => array]
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public function validate(array $parsed)
    {
        if (!isset($parsed['action']) || !isset($parsed['data'])) {
            return ['allowed' => false, 'reason' => 'Invalid parsed command structure'];
        }

        $action = $parsed['action'];
        $data = $parsed['data'];

        // Global safety checks
        $dangerousKeys = ['drop', 'delete', 'truncate', 'exec', 'system', 'shell', 'passthru'];
        $jsonData = json_encode($data);
        foreach ($dangerousKeys as $key) {
            if (stripos($jsonData, $key) !== false) {
                return ['allowed' => false, 'reason' => "Dangerous keyword '{$key}' detected in data"];
            }
        }

        // Action-specific policies
        switch ($action) {
            case 'create_product':
                return $this->validateCreateProduct($data);
            case 'bulk_create_products':
                return $this->validateBulkCreateProducts($data);
            case 'update_product':
                return $this->validateUpdateProduct($data);
            case 'upload_image':
                return $this->validateUploadImage($data);
            case 'update_store_config':
                return $this->validateUpdateStoreConfig($data);
            case 'manage_orders':
                return $this->validateManageOrders($data);
            case 'update_inventory':
                return $this->validateUpdateInventory($data);
            default:
                return ['allowed' => false, 'reason' => "Unknown action: {$action}"];
        }
    }

    /**
     * Validate product creation data.
     */
    protected function validateCreateProduct(array $data)
    {
        // Required fields
        $required = ['name', 'price', 'stock'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['allowed' => false, "reason" => "Missing required field: {$field}"];
            }
        }

        // Price must be positive
        if (!is_numeric($data['price']) || $data['price'] <= 0) {
            return ['allowed' => false, 'reason' => 'Price must be a positive number'];
        }

        // Stock must be integer >= 0
        if (!is_int($data['stock']) && !ctype_digit((string)$data['stock'])) {
            return ['allowed' => false, 'reason' => 'Stock must be an integer'];
        }
        if ((int)$data['stock'] < 0) {
            return ['allowed' => false, 'reason' => 'Stock cannot be negative'];
        }

        // Optional: limit name length
        if (strlen($data['name']) > 255) {
            return ['allowed' => false, 'reason' => 'Product name too long (max 255 characters)'];
        }

        return ['allowed' => true, 'reason' => 'Product creation allowed'];
    }

    /**
     * Validate bulk product creation.
     */
    protected function validateBulkCreateProducts(array $data)
    {
        // Reuse single product validation for each item? We'll validate the bulk data.
        $required = ['name', 'price', 'stock', 'quantity'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['allowed' => false, "reason" => "Missing required field: {$field}"];
            }
        }

        // Price positive
        if (!is_numeric($data['price']) || $data['price'] <= 0) {
            return ['allowed' => false, 'reason' => 'Price must be a positive number'];
        }

        // Stock >= 0
        if (!is_int($data['stock']) && !ctype_digit((string)$data['stock'])) {
            return ['allowed' => false, 'reason' => 'Stock must be an integer'];
        }
        if ((int)$data['stock'] < 0) {
            return ['allowed' => false, 'reason' => 'Stock cannot be negative'];
        }

        // Quantity limit: max 50 per request
        if (!is_int($data['quantity']) && !ctype_digit((string)$data['quantity'])) {
            return ['allowed' => false, 'reason' => 'Quantity must be an integer'];
        }
        $qty = (int)$data['quantity'];
        if ($qty <= 0) {
            return ['allowed' => false, 'reason' => 'Quantity must be positive'];
        }
        if ($qty > 50) {
            return ['allowed' => false, 'reason' => 'Maximum bulk creation is 50 products per request'];
        }

        // Name length
        if (strlen($data['name']) > 255) {
            return ['allowed' => false, 'reason' => 'Product name too long (max 255 characters)'];
        }

        return ['allowed' => true, 'reason' => 'Bulk product creation allowed'];
    }

    /**
     * Validate product update (stub for now - we can expand later).
     */
    protected function validateUpdateProduct(array $data)
    {
        // At least product_id required
        if (!isset($data['product_id'])) {
            return ['allowed' => false, 'reason' => 'Product ID is required for update'];
        }

        // If price is being updated, validate
        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] <= 0)) {
            return ['allowed' => false, 'reason' => 'Price must be a positive number'];
        }

        // If stock is being updated, validate
        if (isset($data['stock'])) {
            if (!is_int($data['stock']) && !ctype_digit((string)$data['stock'])) {
                return ['allowed' => false, 'reason' => 'Stock must be an integer'];
            }
            if ((int)$data['stock'] < 0) {
                return ['allowed' => false, 'reason' => 'Stock cannot be negative'];
            }
        }

        return ['allowed' => true, 'reason' => 'Product update allowed'];
    }

    /**
     * Validate image upload.
     */
    protected function validateUploadImage(array $data)
    {
        if (!isset($data['product_id']) || !isset($data['images']) || !is_array($data['images'])) {
            return ['allowed' => false, 'reason' => 'Product ID and images array are required'];
        }

        // Limit number of images per request
        if (count($data['images']) > 10) {
            return ['allowed' => false, 'reason' => 'Maximum 10 images per upload request'];
        }

        // Each image must be a string URL
        foreach ($data['images'] as $image) {
            if (!is_string($image) || !preg_match('/^https?:\/\//', $image)) {
                return ['allowed' => false, 'reason' => 'Each image must be a valid HTTP or HTTPS URL'];
            }
        }

        return ['allowed' => true, 'reason' => 'Image upload allowed'];
    }

    /**
     * Validate store config update.
     */
    protected function validateUpdateStoreConfig(array $data)
    {
        // At least one setting must be provided
        $allowedKeys = ['store_name', 'currency', 'locale', 'timezone', 'tax_settings'];
        $provided = array_intersect_key($data, array_flip($allowedKeys));
        if (empty($provided)) {
            return ['allowed' => false, 'reason' => 'No valid configuration keys provided'];
        }

        // Validate store name length
        if (isset($data['store_name']) && strlen($data['store_name']) > 255) {
            return ['allowed' => false, 'reason' => 'Store name too long (max 255 characters)'];
        }

        // Validate currency code (3 letters)
        if (isset($data['currency']) && !preg_match('/^[A-Z]{3}$/', $data['currency'])) {
            return ['allowed' => false, 'reason' => 'Currency must be a 3-letter code (e.g., USD, EUR)'];
        }

        // Validate locale (language code, e.g., en, fr, etc.)
        if (isset($data['locale']) && !preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $data['locale'])) {
            return ['allowed' => false, 'reason' => 'Locale must be a valid language code (e.g., en, en_US, fr_FR)'];
        }

        // Validate timezone (PHP timezone identifier)
        if (isset($data['timezone'])) {
            if (!in_array($data['timezone'], timezone_identifiers_list())) {
                return ['allowed' => false, 'reason' => 'Invalid timezone identifier'];
            }
        }

        // Tax settings: if present, must be array
        if (isset($data['tax_settings']) && !is_array($data['tax_settings'])) {
            return ['allowed' => false, 'reason' => 'Tax settings must be an array'];
        }

        return ['allowed' => true, 'reason' => 'Store config update allowed'];
    }

    /**
     * Validate order management.
     */
    protected function validateManageOrders(array $data)
    {
        if (!isset($data['action'])) {
            return ['allowed' => false, 'reason' => 'Order action is required'];
        }

        switch ($data['action']) {
            case 'create':
                // Validate required fields for order creation
                if (!isset($data['customer_email']) || !isset($data['items']) || !is_array($data['items'])) {
                    return ['allowed' => false, 'reason' => 'Customer email and items array are required for order creation'];
                }

                // Validate each item
                foreach ($data['items'] as $index => $item) {
                    if (!isset($item['product_id']) || !isset($item['quantity']) || !isset($item['price'])) {
                        return ['allowed' => false, "reason" => "Item {$index} missing product_id, quantity, or price"];
                    }
                    if (!is_numeric($item['price']) || $item['price'] <= 0) {
                        return ['allowed' => false, "reason" => "Item {$index} price must be positive"];
                    }
                    if (!is_int($item['quantity']) && !ctype_digit((string)$item['quantity'])) {
                        return ['allowed' => false, 'reason' => "Item {$index} quantity must be integer"];
                    }
                    if ((int)$item['quantity'] <= 0) {
                        return ['allowed' => false, 'reason' => "Item {$index} quantity must be positive"];
                    }
                }

                // Limit number of items per order
                if (count($data['items']) > 20) {
                    return ['allowed' => false, 'reason' => 'Maximum 20 items per order'];
                }

                break;
            case 'update_status':
                if (!isset($data['order_id']) || !isset($data['status'])) {
                    return ['allowed' => false, 'reason' => 'Order ID and status are required for status update'];
                }
                // Validate status is a string (we could check against allowed statuses)
                if (!is_string($data['status'])) {
                    return ['allowed' => false, 'reason' => 'Status must be a string'];
                }
                break;
            case 'cancel':
                if (!isset($data['order_id'])) {
                    return ['allowed' => false, 'reason' => 'Order ID is required for cancellation'];
                }
                break;
            default:
                return ['allowed' => false, 'reason' => 'Unknown order action: ' . $data['action']];
        }

        return ['allowed' => true, 'reason' => 'Order management allowed'];
    }

    /**
     * Validate inventory update.
     */
    protected function validateUpdateInventory(array $data)
    {
        if (!isset($data['product_id']) || !isset($data['stock'])) {
            return ['allowed' => false, 'reason' => 'Product ID and stock quantity are required'];
        }

        // Stock must be integer >= 0
        if (!is_int($data['stock']) && !ctype_digit((string)$data['stock'])) {
            return ['allowed' => false, 'reason' => 'Stock must be an integer'];
        }
        if ((int)$data['stock'] < 0) {
            return ['allowed' => false, 'reason' => 'Stock cannot be negative'];
        }

        return ['allowed' => true, 'reason' => 'Inventory update allowed'];
    }
}