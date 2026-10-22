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
 * customer_register_success: queue sign_up (the login that follows registration is not reported)
 */
class QueueSignUp implements ObserverInterface
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
        $this->flag->markRegistered();
        $this->actionEvents->queue(EventCode::SIGN_UP, ['storeId' => (int)$this->storeManager->getStore()->getId()]);
    }
}
