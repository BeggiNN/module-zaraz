<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Page;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\PageContextProviderInterface;
use Perspective\Zaraz\Model\Event\ContextFactory;

/**
 * Context with the current store only (templates filled entirely in the browser, e.g. promotions)
 */
class StoreContext implements PageContextProviderInterface, ArgumentInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param ContextFactory $contextFactory
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ContextFactory $contextFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getContext(): ?EventContextInterface
    {
        return $this->contextFactory->create(['storeId' => (int)$this->storeManager->getStore()->getId()]);
    }
}
