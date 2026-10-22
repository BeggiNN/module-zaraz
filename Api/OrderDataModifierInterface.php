<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Adds or changes properties of an order-level payload (Order Completed, Order Refunded).
 *
 * Register in di.xml: Perspective\Zaraz\Model\Mapper\Modifier\OrderDataModifierPool, argument "modifiers".
 * Top-level custom keys are forwarded by Zaraz to GA4 as event parameters.
 *
 * @api
 */
interface OrderDataModifierInterface
{
    /**
     * Modify mapped order data
     *
     * @param array $data
     * @param OrderInterface $order
     * @return array
     */
    public function modify(array $data, OrderInterface $order): array;
}
