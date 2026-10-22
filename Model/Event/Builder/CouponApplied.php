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
 * "Coupon Applied" — not in the Zaraz e-commerce spec, sent with zaraz.track(). Context data: coupon
 */
class CouponApplied implements EventBuilderInterface
{
    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Coupon Applied';
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
        $coupon = trim((string)$context->getData('coupon'));
        return $coupon !== '' ? ['coupon' => $coupon] : null;
    }
}
