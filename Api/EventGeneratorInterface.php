<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;

/**
 * Generates an event by its code, applying the store configuration (enabled, name override, channel)
 *
 * @api
 */
interface EventGeneratorInterface
{
    public const CHANNEL_BROWSER = 'browser';
    public const CHANNEL_SERVER = 'server';

    /**
     * Event or null when the module/event/channel is disabled or the builder skipped it
     *
     * @param string $code
     * @param EventContextInterface $context
     * @param string $channel
     * @param string|null $id
     * @return EventInterface|null
     */
    public function generate(
        string $code,
        EventContextInterface $context,
        string $channel = self::CHANNEL_BROWSER,
        ?string $id = null,
    ): ?EventInterface;
}
