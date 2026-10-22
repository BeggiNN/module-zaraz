<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Preview;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\App\Emulation;
use Perspective\Zaraz\Api\EventGeneratorInterface;
use Perspective\Zaraz\Model\Event\ContextFactory;
use Perspective\Zaraz\Model\Event\EventCode;

/**
 * Builds the events of a product, an order or a cart with the current settings of a store, without side effects
 * (nothing is queued, no "rendered once" flags are set). Runs in frontend emulation of the store.
 */
class PayloadPreview
{
    public const TYPE_PRODUCT = 'product';
    public const TYPE_ORDER = 'order';
    public const TYPE_CART = 'cart';

    private const EVENTS = [
        self::TYPE_PRODUCT => [
            EventCode::PRODUCT_VIEWED,
            EventCode::PRODUCT_LIST_VIEWED,
            EventCode::PRODUCT_CLICKED,
            EventCode::PRODUCT_ADDED_TO_WISHLIST,
            EventCode::PRODUCT_ADDED_TO_COMPARE,
        ],
        self::TYPE_ORDER => [EventCode::ORDER_COMPLETED],
        self::TYPE_CART => [
            EventCode::CART_VIEWED,
            EventCode::CHECKOUT_STARTED,
            EventCode::SHIPPING_INFO_ENTERED,
            EventCode::PAYMENT_INFO_ENTERED,
        ],
    ];

    /**
     * @param EventGeneratorInterface $generator
     * @param ContextFactory $contextFactory
     * @param Emulation $emulation
     * @param ProductRepositoryInterface $productRepository
     * @param OrderFactory $orderFactory
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly EventGeneratorInterface $generator,
        private readonly ContextFactory $contextFactory,
        private readonly Emulation $emulation,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderFactory $orderFactory,
        private readonly CartRepositoryInterface $cartRepository,
    ) {
    }

    /**
     * Events by code: ['event' => array] or ['skipped' => reason]
     *
     * @param string $type product | order | cart
     * @param string $identifier Product id or SKU, order increment id, quote id
     * @param int $storeId Store for products; orders and carts use their own store
     * @return array<string, array>
     * @throws NoSuchEntityException
     */
    public function preview(string $type, string $identifier, int $storeId): array
    {
        $context = $this->createContext($type, trim($identifier), $storeId);
        $this->emulation->startEnvironmentEmulation($context['storeId'], Area::AREA_FRONTEND, true);
        try {
            $result = [];
            foreach (self::EVENTS[$type] ?? [] as $code) {
                $event = $this->generator->generate($code, $this->contextFactory->create($context));
                $result[$code] = $event !== null
                    ? ['event' => $event->toArray()]
                    : ['skipped' => (string)__('Not sent: disabled in "Events", or skipped by the Orders settings.')];
            }
            return $result;
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    /**
     * Context arguments for the entity
     *
     * @param string $type
     * @param string $identifier
     * @param int $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    private function createContext(string $type, string $identifier, int $storeId): array
    {
        switch ($type) {
            case self::TYPE_PRODUCT:
                $product = ctype_digit($identifier)
                    ? $this->productRepository->getById((int)$identifier, false, $storeId)
                    : $this->productRepository->get($identifier, false, $storeId);
                return [
                    'storeId' => $storeId,
                    'product' => $product,
                    'qty' => 1.0,
                    'data' => [
                        'products' => [$product],
                        'list_id' => 'preview',
                        'list_name' => 'Preview',
                        'position' => 1,
                        'offset' => 0,
                    ],
                ];
            case self::TYPE_ORDER:
                /** @var OrderInterface $order */
                $order = $this->orderFactory->create()->loadByIncrementId($identifier);
                if (!$order->getEntityId()) {
                    throw new NoSuchEntityException(__('Order "%1" does not exist.', $identifier));
                }
                return ['storeId' => (int)$order->getStoreId(), 'order' => $order];
            case self::TYPE_CART:
                $quote = $this->cartRepository->get((int)$identifier);
                return ['storeId' => (int)$quote->getStoreId(), 'quote' => $quote];
            default:
                throw new NoSuchEntityException(__('Unknown preview type "%1".', $type));
        }
    }
}
