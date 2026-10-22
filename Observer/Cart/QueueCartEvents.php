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
use Perspective\Zaraz\Model\Event\ActionEvents;
use Perspective\Zaraz\Model\Event\EventCode;

/**
 * sales_quote_save_after: queue Product Added / Product Removed / coupon events (totals are collected now)
 */
class QueueCartEvents implements ObserverInterface
{
    /**
     * @param ActionEvents $actionEvents
     * @param CartChanges $cartChanges
     */
    public function __construct(
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
        if (!$quote instanceof Quote) {
            return;
        }
        $changes = $this->cartChanges->pull($quote);
        $storeId = (int)$quote->getStoreId();
        foreach ([EventCode::PRODUCT_ADDED => 'added', EventCode::PRODUCT_REMOVED => 'removed'] as $code => $type) {
            foreach ($changes[$type] as $entry) {
                $this->actionEvents->queue(
                    $code,
                    ['storeId' => $storeId, 'quoteItem' => $entry['item'], 'qty' => $entry['qty']]
                );
            }
        }
        $coupon = $changes['coupon'];
        if ($coupon !== null) {
            if ($coupon['removed'] !== '') {
                $this->actionEvents->queue(
                    EventCode::COUPON_REMOVED,
                    ['storeId' => $storeId, 'data' => ['coupon' => $coupon['removed']]]
                );
            }
            if ($coupon['applied'] !== '') {
                $this->actionEvents->queue(
                    EventCode::COUPON_APPLIED,
                    ['storeId' => $storeId, 'data' => ['coupon' => $coupon['applied']]]
                );
            }
        }
    }
}
