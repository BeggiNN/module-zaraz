<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Event\BuilderPool;
use Perspective\Zaraz\Model\Event\EventCode;
use Perspective\Zaraz\Model\Preview\PayloadPreview;

/**
 * Data of the Preview Payload page
 */
class Preview implements ArgumentInterface
{
    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Event names the Zaraz tools send for e-commerce events with default names (Managed Components
     * source); null = not sent by that tool, "custom" = sent under its own name
     */
    private const DESTINATIONS = [
        EventCode::PRODUCT_VIEWED => ['GA4' => 'view_item', 'Meta' => 'ViewContent', 'TikTok' => 'ViewContent'],
        EventCode::PRODUCT_LIST_VIEWED => ['GA4' => 'view_item_list', 'Meta' => 'custom', 'TikTok' => null],
        EventCode::PRODUCT_CLICKED => ['GA4' => 'select_item', 'Meta' => 'custom', 'TikTok' => null],
        EventCode::PRODUCT_ADDED_TO_WISHLIST => [
            'GA4' => 'add_to_wishlist',
            'Meta' => 'AddToWishlist',
            'TikTok' => 'AddToWishlist',
        ],
        EventCode::CART_VIEWED => ['GA4' => 'view_cart', 'Meta' => 'custom', 'TikTok' => null],
        EventCode::CHECKOUT_STARTED => [
            'GA4' => 'begin_checkout',
            'Meta' => 'InitiateCheckout',
            'TikTok' => 'InitiateCheckout',
        ],
        EventCode::SHIPPING_INFO_ENTERED => ['GA4' => 'add_shipping_info', 'Meta' => 'custom', 'TikTok' => null],
        EventCode::PAYMENT_INFO_ENTERED => [
            'GA4' => 'add_payment_info',
            'Meta' => 'AddPaymentInfo',
            'TikTok' => 'AddPaymentInfo',
        ],
        EventCode::ORDER_COMPLETED => ['GA4' => 'purchase', 'Meta' => 'Purchase', 'TikTok' => 'CompletePayment'],
    ];

    /**
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param PayloadPreview $payloadPreview
     * @param BuilderPool $builderPool
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly PayloadPreview $payloadPreview,
        private readonly BuilderPool $builderPool,
        private readonly Escaper $escaper,
    ) {
    }

    /**
     * Submitted form values
     *
     * @return array{type: string, id: string, store: int}
     */
    public function getParams(): array
    {
        $stores = $this->getStores();
        $type = (string)$this->request->getParam('type', PayloadPreview::TYPE_PRODUCT);
        return [
            'type' => array_key_exists($type, $this->getTypes()) ? $type : PayloadPreview::TYPE_PRODUCT,
            'id' => trim((string)$this->request->getParam('entity')),
            'store' => (int)$this->request->getParam('store', (int)array_key_first($stores)),
        ];
    }

    /**
     * Store views: id => name
     *
     * @return array<int, string>
     */
    public function getStores(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[(int)$store->getId()] = $store->getWebsite()->getName() . ' / ' . $store->getName();
        }
        return $stores;
    }

    /**
     * Entity types: value => [label, placeholder]
     *
     * @return array<string, array{label: string, placeholder: string}>
     */
    public function getTypes(): array
    {
        return [
            PayloadPreview::TYPE_PRODUCT => [
                'label' => (string)__('Product'),
                'placeholder' => (string)__('SKU or product ID, e.g. MJ06'),
            ],
            PayloadPreview::TYPE_ORDER => [
                'label' => (string)__('Order'),
                'placeholder' => (string)__('Order increment ID, e.g. 000000013'),
            ],
            PayloadPreview::TYPE_CART => [
                'label' => (string)__('Cart'),
                'placeholder' => (string)__('Quote ID, e.g. 28'),
            ],
        ];
    }

    /**
     * Preview result: ['events' => list of cards] or ['error' => message]; empty without input
     *
     * A card: code, skipped (reason|null), method, name, renamed (Zaraz name differs from the spec default),
     * payload (array), products (array), summary (label => value), destinations (tool => event|null), json, html
     *
     * @return array
     */
    public function getResult(): array
    {
        $params = $this->getParams();
        if ($params['id'] === '') {
            return [];
        }
        try {
            $cards = [];
            $rows = $this->payloadPreview->preview($params['type'], $params['id'], $params['store']);
            foreach ($rows as $code => $row) {
                $cards[] = isset($row['event'])
                    ? $this->createCard((string)$code, $row['event'])
                    : ['code' => (string)$code, 'skipped' => (string)$row['skipped']];
            }
            return ['events' => $cards];
        } catch (LocalizedException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Card data of a generated event
     *
     * @param string $code
     * @param array $event
     * @return array
     */
    private function createCard(string $code, array $event): array
    {
        $payload = json_decode((string)json_encode($event['payload']), true) ?: [];
        $builder = $this->builderPool->get($code);
        $json = (string)json_encode(
            ['method' => $event['method'], 'name' => $event['name'], 'payload' => (object)$payload],
            self::JSON_FLAGS
        );

        return [
            'code' => $code,
            'skipped' => null,
            'method' => (string)$event['method'],
            'name' => (string)$event['name'],
            'renamed' => $builder !== null && $builder->getDefaultName() !== $event['name'],
            'payload' => $payload,
            'products' => $payload['products'] ?? (isset($payload['product_id']) ? [$payload] : []),
            'summary' => $this->getSummary($payload),
            'destinations' => self::DESTINATIONS[$code] ?? [],
            'json' => $json,
            'html' => $this->highlight($json),
        ];
    }

    /**
     * Key figures of a payload
     *
     * @param array $payload
     * @return array<string, string>
     */
    private function getSummary(array $payload): array
    {
        $currency = (string)($payload['currency'] ?? '');
        $money = static fn ($amount): string => number_format((float)$amount, 2, '.', ' ') . ' ' . $currency;
        $summary = [];
        if (isset($payload['order_id'])) {
            $summary[(string)__('Order')] = '#' . $payload['order_id'];
        }
        if (isset($payload['total'])) {
            $summary[(string)__('Total')] = $money($payload['total']);
        }
        if (isset($payload['revenue'])) {
            $summary[(string)__('Revenue')] = $money($payload['revenue']);
        }
        if (isset($payload['value']) && !isset($payload['total'])) {
            $summary[(string)__('Value')] = $money($payload['value']);
        }
        if (isset($payload['shipping'])) {
            $summary[(string)__('Shipping')] = $money($payload['shipping']);
        }
        if (isset($payload['products'])) {
            $summary[(string)__('Products')] = (string)count($payload['products']);
        }
        // Event-level "category" is the list name only for list events; single-product events carry list_name
        if (isset($payload['products'], $payload['list_id'], $payload['category'])) {
            $summary[(string)__('List')] = (string)$payload['category'];
        } elseif (isset($payload['list_name'])) {
            $summary[(string)__('List')] = (string)$payload['list_name'];
        }
        if (isset($payload['coupon'])) {
            $summary[(string)__('Coupon')] = (string)$payload['coupon'];
        }
        return $summary;
    }

    /**
     * Escaped JSON with syntax highlighting spans
     *
     * @param string $json
     * @return string
     */
    private function highlight(string $json): string
    {
        $escaped = $this->escaper->escapeHtml($json);
        return (string)preg_replace_callback(
            '/(&quot;.*?&quot;)(\s*:)?|\b(true|false|null)\b|(-?\b\d+(?:\.\d+)?\b)/',
            static function (array $match): string {
                if ($match[1] !== '') {
                    $class = isset($match[2]) && $match[2] !== '' ? 'pz-json-key' : 'pz-json-string';
                    return '<span class="' . $class . '">' . $match[1] . '</span>' . ($match[2] ?? '');
                }
                if (($match[3] ?? '') !== '') {
                    return '<span class="pz-json-literal">' . $match[3] . '</span>';
                }
                return '<span class="pz-json-number">' . $match[4] . '</span>';
            },
            $escaped
        );
    }
}
