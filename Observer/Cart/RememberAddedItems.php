<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Observer\Cart;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Perspective\Zaraz\Model\Cart\CartChanges;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Event\ActionEvents;

/**
 * sales_quote_product_add_after: remember the quantity added to visible items
 */
class RememberAddedItems implements ObserverInterface
{
    /**
     * @param Config $config
     * @param ActionEvents $actionEvents
     * @param CartChanges $cartChanges
     */
    public function __construct(
        private readonly Config $config,
        private readonly ActionEvents $actionEvents,
        private readonly CartChanges $cartChanges,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        foreach ((array)$observer->getEvent()->getData('items') as $item) {
            if (!$item instanceof AbstractItem || $item->getParentItem()) {
                continue;
            }
            if (!$this->config->isEnabled((int)$item->getStoreId()) || !$this->actionEvents->isAvailable()) {
                return;
            }
            $qty = (float)$item->getData('qty_to_add');
            if ($qty > 0) {
                $this->cartChanges->added($item, $qty);
            }
        }
    }
}
