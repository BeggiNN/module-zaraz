<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Builder;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Model\Event\Payload\ProductPayload;

/**
 * "Product Added to Compare" — not in the Zaraz e-commerce spec, sent with zaraz.track() (needs a trigger in Zaraz)
 */
class ProductAddedToCompare implements EventBuilderInterface
{
    /**
     * @param ProductPayload $productPayload
     */
    public function __construct(
        private readonly ProductPayload $productPayload,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Product Added to Compare';
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return EventInterface::METHOD_TRACK;
    }

    /**
     * @inheritDoc
     */
    public function build(EventContextInterface $context): ?array
    {
        $product = $context->getProduct();
        return $product ? $this->productPayload->build($product, $context->getStoreId()) : null;
    }
}
