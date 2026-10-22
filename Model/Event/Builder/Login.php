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
 * "login" — customer login, zaraz.track() (GA4 recommended event name). Context data: method
 */
class Login implements EventBuilderInterface
{
    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'login';
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
        return ['method' => (string)($context->getData('method') ?? 'magento')];
    }
}
