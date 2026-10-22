<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Observer\Customer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Customer\AuthenticationFlag;
use Perspective\Zaraz\Model\Event\ActionEvents;
use Perspective\Zaraz\Model\Event\EventCode;

/**
 * customer_login: queue login
 */
class QueueLogin implements ObserverInterface
{
    /**
     * @param ActionEvents $actionEvents
     * @param StoreManagerInterface $storeManager
     * @param AuthenticationFlag $flag
     */
    public function __construct(
        private readonly ActionEvents $actionEvents,
        private readonly StoreManagerInterface $storeManager,
        private readonly AuthenticationFlag $flag,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        if (!$this->flag->isRegistered()) {
            $this->actionEvents->queue(EventCode::LOGIN, ['storeId' => (int)$this->storeManager->getStore()->getId()]);
        }
    }
}
