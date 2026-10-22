<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api;

use Perspective\Zaraz\Api\Data\EventContextInterface;

/**
 * Builds the payload of one Zaraz event.
 *
 * Builders are registered in Perspective\Zaraz\Model\Event\BuilderPool via di.xml under an event code;
 * another module adds an event or replaces a builder by declaring the same code.
 *
 * @api
 */
interface EventBuilderInterface
{
    /**
     * Event name from the Zaraz spec, e.g. "Product Viewed" (can be overridden in the admin)
     *
     * @return string
     */
    public function getDefaultName(): string;

    /**
     * Zaraz Web API method, one of EventInterface::METHOD_*
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Event properties, or null when the event must not be sent for this context
     *
     * @param EventContextInterface $context
     * @return array|null
     */
    public function build(EventContextInterface $context): ?array;
}
