<?php

namespace App\Services\Storefront;

use App\Contact;

/**
 * Tool implementations for the storefront AI support agent (Phase B).
 */
class SupportToolRunner
{
    public function __construct(
        private CheckoutService $checkout,
        private CatalogService $catalog,
        private AvailabilityService $availability,
        private RepairStatusLookupService $repairs,
        private DeviceTrackLookupService $devices,
        private StorefrontSettingService $settings,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toolDefinitions(bool $authenticated): array
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'find_product',
                    'description' => 'Search the storefront catalog by product name or SKU.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search text'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'store_availability',
                    'description' => 'Check which branches have a product in stock.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => ['type' => 'integer'],
                            'variation_id' => ['type' => 'integer'],
                        ],
                        'required' => ['product_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_contact_channels',
                    'description' => 'Return hotline, WhatsApp, and contact-page handoff channels for human support.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) [],
                    ],
                ],
            ],
        ];

        if (! $authenticated) {
            return $tools;
        }

        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'get_my_orders',
                'description' => 'List recent orders for the signed-in customer.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'integer'],
                        'payment_status' => [
                            'type' => 'string',
                            'enum' => ['due', 'paid', 'pending', 'failed'],
                        ],
                    ],
                ],
            ],
        ];
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'get_order_detail',
                'description' => 'Get one order by id for the signed-in customer only.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer'],
                    ],
                    'required' => ['order_id'],
                ],
            ],
        ];
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'get_my_repairs',
                'description' => 'List repair job sheets for the signed-in customer.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
        $tools[] = [
            'type' => 'function',
            'function' => [
                'name' => 'get_my_device_services',
                'description' => 'List console/device service records for the signed-in customer phone.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(
        string $name,
        array $arguments,
        int $businessId,
        ?Contact $contact,
        string $locale
    ): array {
        return match ($name) {
            'find_product' => $this->findProduct($businessId, (string) ($arguments['query'] ?? ''), $locale),
            'store_availability' => $this->storeAvailability(
                $businessId,
                (int) ($arguments['product_id'] ?? 0),
                isset($arguments['variation_id']) ? (int) $arguments['variation_id'] : null
            ),
            'get_contact_channels' => $this->contactChannels($businessId),
            'get_my_orders' => $this->myOrders($businessId, $contact, $arguments),
            'get_order_detail' => $this->orderDetail($businessId, $contact, (int) ($arguments['order_id'] ?? 0)),
            'get_my_repairs' => $this->myRepairs($businessId, $contact, $locale),
            'get_my_device_services' => $this->myDevices($contact),
            default => ['error' => 'Unknown tool'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function findProduct(int $businessId, string $query, string $locale): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['products' => []];
        }

        $items = $this->catalog->search($businessId, $query, 6, $locale);
        $products = array_map(static function (array $p): array {
            return [
                'id' => $p['id'] ?? null,
                'name' => $p['name'] ?? null,
                'slug' => $p['slug'] ?? null,
                'price' => $p['price'] ?? ($p['sell_price_inc_tax'] ?? null),
                'in_stock' => $p['in_stock'] ?? null,
            ];
        }, $items);

        return ['products' => $products];
    }

    /**
     * @return array<string, mixed>
     */
    private function storeAvailability(int $businessId, int $productId, ?int $variationId): array
    {
        if ($productId < 1) {
            return ['error' => 'product_id required'];
        }

        $data = $this->availability->getAvailability($businessId, $productId, $variationId);
        if ($data === null) {
            return ['error' => 'Product not found'];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function contactChannels(int $businessId): array
    {
        $settings = $this->settings->get($businessId);
        $contact = is_array($settings['contact'] ?? null) ? $settings['contact'] : [];

        return [
            'phone' => $contact['phone'] ?? null,
            'whatsapp' => $contact['whatsapp'] ?? null,
            'contact_path' => '/contact',
            'faq_path' => '/faq',
            'stores_path' => '/stores',
            'repair_path' => '/repair-status',
            'track_console_path' => '/track-console',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function myOrders(int $businessId, ?Contact $contact, array $arguments): array
    {
        if (! $contact) {
            return ['error' => 'Sign in required'];
        }

        $page = max(1, (int) ($arguments['page'] ?? 1));
        $status = isset($arguments['payment_status']) ? (string) $arguments['payment_status'] : null;
        $result = $this->checkout->listOrdersForContact($businessId, $contact->id, $page, 10, $status);

        $orders = array_map(static function (array $o): array {
            return [
                'id' => $o['id'] ?? null,
                'invoice_no' => $o['invoice_no'] ?? null,
                'storefront_order_id' => $o['storefront_order_id'] ?? null,
                'status' => $o['status'] ?? null,
                'payment_status' => $o['payment_status'] ?? null,
                'shipping_status' => $o['shipping_status'] ?? null,
                'tracking_no' => $o['tracking_no'] ?? ($o['shipping_details'] ?? null),
                'final_total' => $o['final_total'] ?? null,
                'created_at' => $o['created_at'] ?? null,
            ];
        }, $result['orders'] ?? []);

        return ['orders' => $orders, 'meta' => $result['meta'] ?? []];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderDetail(int $businessId, ?Contact $contact, int $orderId): array
    {
        if (! $contact) {
            return ['error' => 'Sign in required'];
        }
        if ($orderId < 1) {
            return ['error' => 'order_id required'];
        }

        $order = $this->checkout->getOrderForContact($businessId, $contact->id, $orderId);
        if (empty($order)) {
            return ['error' => 'Order not found'];
        }

        return ['order' => $order];
    }

    /**
     * @return array<string, mixed>
     */
    private function myRepairs(int $businessId, ?Contact $contact, string $locale): array
    {
        if (! $contact) {
            return ['error' => 'Sign in required'];
        }
        if (! $this->repairs->isAvailable($businessId)) {
            return ['error' => 'Repair tracking is not available'];
        }

        return [
            'repairs' => $this->repairs->forContact($businessId, $contact, $locale),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function myDevices(?Contact $contact): array
    {
        if (! $contact) {
            return ['error' => 'Sign in required'];
        }
        if (! $this->devices->isConfigured()) {
            return ['error' => 'Device tracking is not available'];
        }

        $phone = trim((string) ($contact->mobile ?? ''));
        if ($phone === '') {
            return ['count' => 0, 'services' => []];
        }

        $result = $this->devices->trackByPhone($phone);
        if (in_array($result['status'] ?? 0, [429, 503], true)) {
            return ['error' => $result['message'] ?? 'Device tracking failed'];
        }

        return [
            'count' => count($result['services'] ?? []),
            'services' => $result['services'] ?? [],
        ];
    }
}
