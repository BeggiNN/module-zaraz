<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\CustomerData;

use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Customer\UserProperties;
use Perspective\Zaraz\Model\Event\EventQueue;
use Perspective\Zaraz\Model\Request\DebugAllowed;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;

class EventsSection implements SectionSourceInterface
{
    /**
     * @param Config $config
     * @param EventQueue $queue
     * @param DebugAllowed $debugAllowed
     * @param StoreManagerInterface $storeManager
     * @param UserProperties $userProperties
     */
    public function __construct(
        private readonly Config $config,
        private readonly EventQueue $queue,
        private readonly DebugAllowed $debugAllowed,
        private readonly StoreManagerInterface $storeManager,
        private readonly UserProperties $userProperties,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getSectionData(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return ['events' => [], 'user' => [], 'debug' => false];
        }
        return [
            'events' => $this->queue->pull(),
            'user' => array_map(
                static fn ($event): array => $event->toArray(),
                $this->userProperties->getCustomerEvents($storeId)
            ),
            'debug' => $this->debugAllowed->isAllowedForCurrentVisitor($storeId),
        ];
    }
}
