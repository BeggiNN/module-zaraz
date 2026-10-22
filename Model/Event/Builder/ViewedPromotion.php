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
 * "Viewed Promotion" (template): the JS adapter fills promotion_id, name, creative, position from data-zaraz-
 * promotion-* attributes of a visible element
 */
class ViewedPromotion implements EventBuilderInterface
{
    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Viewed Promotion';
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
        return [];
    }
}
