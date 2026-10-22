<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper\Modifier;

use Perspective\Zaraz\Api\OrderDataModifierInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Runs order data modifiers registered in di.xml
 */
class OrderDataModifierPool
{
    /**
     * @param OrderDataModifierInterface[] $modifiers
     */
    public function __construct(
        private readonly array $modifiers = [],
    ) {
    }

    /**
     * Apply all modifiers
     *
     * @param array $data
     * @param OrderInterface $order
     * @return array
     */
    public function modify(array $data, OrderInterface $order): array
    {
        foreach ($this->modifiers as $modifier) {
            $data = $modifier->modify($data, $order);
        }
        return $data;
    }
}
