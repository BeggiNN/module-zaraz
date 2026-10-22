<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Observer\Catalog;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Event\ActionEvents;
use Perspective\Zaraz\Model\Event\EventCode;

/**
 * catalog_product_compare_add_product: queue Product Added to Compare
 */
class QueueAddedToCompare implements ObserverInterface
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
        if ($product instanceof ProductInterface) {
            $this->actionEvents->queue(EventCode::PRODUCT_ADDED_TO_COMPARE, [
                'storeId' => (int)$this->storeManager->getStore()->getId(),
                'product' => $product,
            ]);
        }
    }
}
