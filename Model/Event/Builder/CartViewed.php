<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Builder;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Model\Event\Payload\CartPayload;

/**
 * "Cart Viewed" — shopping cart page
 */
class CartViewed implements EventBuilderInterface
{
    /**
     * @param CartPayload $cartPayload
     */
    public function __construct(
        private readonly CartPayload $cartPayload,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Cart Viewed';
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return EventInterface::METHOD_ECOMMERCE;
    }

    /**
     * @inheritDoc
     */
    public function build(EventContextInterface $context): ?array
    {
        $quote = $context->getQuote();
        return $quote ? $this->cartPayload->build($quote) : null;
    }
}
