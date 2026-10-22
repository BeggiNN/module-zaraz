<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Page;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\PageContextProviderInterface;
use Perspective\Zaraz\Model\Event\ContextFactory;

/**
 * Current cart for the cart and checkout pages (both are not cached by FPC; the block is cacheable="false")
 */
class CartPageContext implements PageContextProviderInterface, ArgumentInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param ContextFactory $contextFactory
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ContextFactory $contextFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getContext(): ?EventContextInterface
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId() || !$quote->getItemsCount()) {
            return null;
        }
        return $this->contextFactory->create(['storeId' => (int)$quote->getStoreId(), 'quote' => $quote]);
    }
}
