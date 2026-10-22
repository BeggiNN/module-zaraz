<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Observer\Cart;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Perspective\Zaraz\Model\Cart\CartChanges;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Event\ActionEvents;

/**
 * sales_quote_save_before: quantity differences and coupon changes
 */
class CollectCartChanges implements ObserverInterface
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
        $quote = $observer->getEvent()->getData('quote');
        if ($quote instanceof Quote
            // Only explicitly inactive quotes (placed orders) are skipped: a new quote has no is_active yet
            && ($quote->getData('is_active') === null || (bool)$quote->getIsActive())
            && $this->config->isEnabled((int)$quote->getStoreId())
            && $this->actionEvents->isAvailable()
        ) {
            $this->cartChanges->collect($quote);
        }
    }
}
