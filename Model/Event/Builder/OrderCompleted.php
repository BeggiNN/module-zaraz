<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Builder;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Event\DeliveryId;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\Modifier\OrderDataModifierPool;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\OrderTotals;
use Perspective\Zaraz\Model\Price\PriceResolver;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * "Order Completed" — placed order
 *
 * event_id = "order-<increment_id>" in the browser and in the server fallback (Perspective_ZarazServerSide), so the
 * Zaraz Meta / Pinterest Conversions API components deduplicate the purchase (the Zaraz TikTok tool ignores event_id)
 */
class OrderCompleted implements EventBuilderInterface
{
    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param LineResolver $lineResolver
     * @param ProductMapper $productMapper
     * @param PriceResolver $priceResolver
     * @param OrderTotals $orderTotals
     * @param OrderDataModifierPool $modifierPool
     * @param DeliveryId $deliveryId
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LineResolver $lineResolver,
        private readonly ProductMapper $productMapper,
        private readonly PriceResolver $priceResolver,
        private readonly OrderTotals $orderTotals,
        private readonly OrderDataModifierPool $modifierPool,
        private readonly DeliveryId $deliveryId,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Order Completed';
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return EventInterface::METHOD_ECOMMERCE;
    }

    /**
     * @inheritDoc
     */
    public function build(EventContextInterface $context): ?array
    {
        $order = $context->getOrder();
        if ($order === null || !$this->isTrackable($order)) {
            return null;
        }
        $storeId = (int)$order->getStoreId();

        $lines = [];
        foreach ($order->getItems() as $item) {
            if (!$item->getParentItemId() && !$item->getParentItem()) {
                $lines[] = $this->lineResolver->fromOrderItem($item);
            }
        }

        $data = [
            'checkout_id' => (string)$order->getQuoteId(),
            'order_id' => (string)$order->getIncrementId(),
            'event_id' => $this->deliveryId->forOrder((string)$order->getIncrementId()),
            'affiliation' => $this->getAffiliation($storeId),
            ...$this->orderTotals->calculate($order),
            'coupon' => (string)$order->getCouponCode(),
            'currency' => $this->priceResolver->getOrderCurrency($order),
            'products' => $this->productMapper->mapList($lines, $storeId),
        ];
        $data = $this->modifierPool->modify($data, $order);

        return array_filter($data, static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * "Orders" settings: zero total and excluded statuses
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isTrackable(OrderInterface $order): bool
    {
        $storeId = (int)$order->getStoreId();
        if ($this->config->isSkipZeroTotalOrders($storeId) && (float)$order->getBaseGrandTotal() <= 0.0) {
            return false;
        }
        return !in_array((string)$order->getStatus(), $this->config->getSkipOrderStatuses($storeId), true);
    }

    /**
     * Configured affiliation or the store name
     *
     * @param int $storeId
     * @return string
     */
    private function getAffiliation(int $storeId): string
    {
        return $this->config->getAffiliation($storeId)
            ?: (string)$this->storeManager->getStore($storeId)->getFrontendName();
    }
}
