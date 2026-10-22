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

/**
 * sales_quote_merge_after: changes of a merged quote (login) are not customer actions
 */
class MarkMergedQuote implements ObserverInterface
{
    /**
     * @param CartChanges $cartChanges
     */
    public function __construct(
        private readonly CartChanges $cartChanges,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $quote = $observer->getEvent()->getData('quote');
        if ($quote instanceof Quote) {
            $this->cartChanges->merged($quote);
        }
    }
}
