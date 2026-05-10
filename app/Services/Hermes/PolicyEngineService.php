<?php

namespace App\Services\Hermes;

class PolicyEngineService
{
    protected $allowedActions = [
        'create_product',
        'bulk_create_products',
        'upload_image',
        'update_store_config',
        'manage_orders',
        'update_inventory',
    ];

    protected $allowedConfigKeys = [
        'shop.name',
        'shop.description',
        'currency',
        'locale',
        'tax',
        'shipping',
        // Add more as needed, but be cautious
    ];

    public function validate($parsed)
    {
        if (!isset($parsed['action']) || !isset($parsed['data'])) {
            return false;
        }

        $action = $parsed['action'];
        $data = $parsed['data'];

        // Check if action is allowed
        if (!in_array($action, $this->allowedActions)) {
            return false;
        }

        // Validate based on action
        switch ($action) {
            case 'create_product':
            case 'bulk_create_products':
                return $this->validateProductData($data);
            case 'upload_image':
                return $this->validateImageData($data);
            case 'update_store_config':
                return $this->validateConfigData($data);
            case 'manage_orders':
            case 'update_inventory':
                // For now, we'll allow these but in a real system you'd have more validation
                return true;
            default:
                return false;
        }
    }

    protected function validateProductData($data)
    {
        // Check required fields
        if (!isset($data['name']) || !isset($data['price']) || !isset($data['stock'])) {
            return false;
        }

        // Price and stock must be numeric and non-negative
        if (!is_numeric($data['price']) || $data['price'] < 0) {
            return false;
        }
        if (!is_numeric($data['stock']) || $data['stock'] < 0) {
            return false;
        }

        // Quantity (if present) must be positive integer
        if (isset($data['quantity']) && (!is_numeric($data['quantity']) || $data['quantity'] <= 0)) {
            return false;
        }

        return true;
    }

    protected function validateImageData($data)
    {
        // We expect either a URL or a file upload (but in our API we'll handle file uploads separately)
        // For simplicity, we'll just check that we have a URL or a base64 string.
        return isset($data['url']) || isset($data['base64']);
    }

    protected function validateConfigData($data)
    {
        if (!isset($data['key']) || !isset($data['value'])) {
            return false;
        }

        // Check if the key is in the allowed list
        return in_array($data['key'], $this->allowedConfigKeys);
    }
}