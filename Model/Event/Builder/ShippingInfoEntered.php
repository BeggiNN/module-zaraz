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
 * "Shipping Info Entered" (template): the JS adapter adds shipping_tier when the shipping step is saved
 */
class ShippingInfoEntered implements EventBuilderInterface
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
        return 'Shipping Info Entered';
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
