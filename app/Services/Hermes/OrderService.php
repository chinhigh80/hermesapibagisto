<?php

namespace App\Services\Hermes;

use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderItemRepository;
use Webkul\Sales\Repositories\OrderStatusRepository;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Product\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    protected $orderRepository;
    protected $orderItemRepository;
    protected $orderStatusRepository;
    protected $customerRepository;
    protected $productRepository;

    public function __construct(
        OrderRepository $orderRepository,
        OrderItemRepository $orderItemRepository,
        OrderStatusRepository $orderStatusRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->orderStatusRepository = $orderStatusRepository;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * Manage orders: create, update status, cancel.
     *
     * Expected $data structure:
     *   For creating an order:
     *     [
     *         'action' => 'create',
     *         'customer_email' => 'john@example.com',
     *         'items' => [
     *             ['product_id' => 1, 'quantity' => 2, 'price' => 10.00],
     *             ...
     *         ],
     *         'billing_address' => [...],
     *         'shipping_address' => [...],
     *         ...
     *     ]
     *
     *   For updating status:
     *     [
     *         'action' => 'update_status',
     *         'order_id' => 5,
     *         'status' => 'processing'   // must be a valid order status code
     *     ]
     *
     *   For cancelling:
     *     [
     *         'action' => 'cancel',
     *         'order_id' => 5
     *     ]
     *
     * @param array $data
     * @return array
     */
    public function manage(array $data)
    {
        if (!isset($data['action'])) {
            throw new Exception('Action is required');
        }

        switch ($data['action']) {
            case 'create':
                return $this->createOrder($data);
            case 'update_status':
                return $this->updateOrderStatus($data);
            case 'cancel':
                return $this->cancelOrder($data);
            default:
                throw new Exception('Unknown order action: ' . $data['action']);
        }
    }

    /**
     * Create a new order.
     *
     * @param array $data
     * @return array
     */
    protected function createOrder(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Validate required fields
            if (!isset($data['customer_email']) || !isset($data['items'])) {
                throw new Exception('Customer email and items are required to create an order');
            }

            // Find or create customer
            $customer = $this->customerRepository->findOneBy(['email' => $data['customer_email']]);
            if (!$customer) {
                // Create a new customer (minimal data)
                $customerData = [
                    'email' => $data['customer_email'],
                    'first_name' => isset($data['customer_first_name']) ? $data['customer_first_name'] : '',
                    'last_name' => isset($data['customer_last_name']) ? $data['customer_last_name'] : '',
                    // ... other required fields
                ];
                $customer = $this->customerRepository->create($customerData);
            }

            // Get default order status (e.g., pending)
            $pendingStatus = $this->orderStatusRepository->findOneBy(['code' => 'pending']);
            if (!$pendingStatus) {
                throw new Exception('Pending order status not found');
            }

            // Prepare order data
            $orderData = [
                'customer_id' => $customer->id,
                'billing_address' => isset($data['billing_address']) ? $data['billing_address'] : [],
                'shipping_address' => isset($data['shipping_address']) ? $data['shipping_address'] : [],
                'order_status_id' => $pendingStatus->id,
                'currency_code' => isset($data['currency']) ? $data['currency'] : 'USD',
                'currency_value' => 1.0, // assuming base currency
                'discount_amount' => 0,
                'tax_amount' => 0,
                'shipping_amount' => 0,
                'total_amount' => 0, // will calculate from items
            ];

            $order = $this->orderRepository->create($orderData);

            $totalAmount = 0;
            foreach ($data['items'] as $itemData) {
                if (!isset($itemData['product_id']) || !isset($itemData['quantity']) || !isset($itemData['price'])) {
                    throw new Exception('Each item must have product_id, quantity, and price');
                }

                $product = $this->productRepository->find($itemData['product_id']);
                if (!$product) {
                    throw new Exception("Product not found: {$itemData['product_id']}");
                }

                $itemTotal = $itemData['price'] * $itemData['quantity'];
                $totalAmount += $itemTotal;

                $orderItemData = [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => $itemData['price'],
                    'product_quantity' => $itemData['quantity'],
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'row_total' => $itemTotal,
                ];

                $this->orderItemRepository->create($orderItemData);
            }

            // Update order total
            $order->update([
                'total_amount' => $totalAmount,
                'invoice_amount' => $totalAmount,
                'paid_amount' => 0,
                'amount_refunded' => 0,
            ]);

            return $order;
        });
    }

    /**
     * Update the status of an order.
     *
     * @param array $data ['order_id' => int, 'status' => string]
     * @return array
     */
    protected function updateOrderStatus(array $data)
    {
        if (!isset($data['order_id']) || !isset($data['status'])) {
            throw new Exception('Order ID and status are required');
        }

        $order = $this->orderRepository->find($data['order_id']);
        if (!$order) {
            throw new Exception("Order not found: {$data['order_id']}");
        }

        // Find the status by code
        $status = $this->orderStatusRepository->findOneBy(['code' => $data['status']]);
        if (!$status) {
            throw new Exception("Invalid order status: {$data['status']}");
        }

        $order->update([
            'order_status_id' => $status->id,
        ]);

        return $order;
    }

    /**
     * Cancel an order.
     *
     * @param array $data ['order_id' => int]
     * @return array
     */
    protected function cancelOrder(array $data)
    {
        if (!isset($data['order_id'])) {
            throw new Exception('Order ID is required');
        }

        $order = $this->orderRepository->find($data['order_id']);
        if (!$order) {
            throw new Exception("Order not found: {$data['order_id']}");
        }

        // Find the cancelled status
        $cancelledStatus = $this->orderStatusRepository->findOneBy(['code' => 'cancelled']);
        if (!$cancelledStatus) {
            throw new Exception('Cancelled order status not found');
        }

        $order->update([
            'order_status_id' => $cancelledStatus->id,
        ]);

        // Optionally, restore stock? This depends on business logic.
        // For now, we just change the status.

        return $order;
    }
}