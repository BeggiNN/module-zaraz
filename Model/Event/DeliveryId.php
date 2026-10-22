<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event;

use Magento\Framework\Math\Random;

/**
 * Delivery ids for at-most-once events
 */
class DeliveryId
{
    /**
     * @param Random $random
     */
    public function __construct(
        private readonly Random $random,
    ) {
    }

    /**
     * Unique id for a one-off action event
     *
     * @param string $code
     * @return string
     */
    public function generate(string $code): string
    {
        return $code . '-' . $this->random->getRandomString(12, Random::CHARS_LOWERS . Random::CHARS_DIGITS);
    }

    /**
     * Stable id of an order (Order Completed is sent once per browser even if the page is reloaded)
     *
     * @param string $incrementId
     * @return string
     */
    public function forOrder(string $incrementId): string
    {
        return 'order-' . $incrementId;
    }
}
