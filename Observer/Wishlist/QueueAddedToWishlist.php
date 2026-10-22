<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Observer\Wishlist;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Event\ActionEvents;
use Perspective\Zaraz\Model\Event\EventCode;

/**
 * wishlist_add_product: queue Product Added to Wishlist
 */
class QueueAddedToWishlist implements ObserverInterface
{
    /**
     * @param ActionEvents $actionEvents
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ActionEvents $actionEvents,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        if (!$product instanceof ProductInterface) {
            return;
        }
        $item = $observer->getEvent()->getData('item');
        $this->actionEvents->queue(EventCode::PRODUCT_ADDED_TO_WISHLIST, [
            'storeId' => (int)$this->storeManager->getStore()->getId(),
            'product' => $product,
            'qty' => is_object($item) ? ((float)$item->getQty() ?: 1.0) : 1.0,
        ]);
    }
}
