<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Builder;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;

/**
 * "Products Searched" — search results page. Context data: query
 */
class ProductsSearched implements EventBuilderInterface
{
    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Products Searched';
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
        $query = trim((string)$context->getData('query'));
        return $query !== '' ? ['query' => $query] : null;
    }
}
