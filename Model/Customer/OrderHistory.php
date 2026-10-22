<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Customer;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class OrderHistory
{
    /**
     * @var array<string, array{count: int, value: float}>
     */
    private array $stats = [];

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource,
    ) {
    }

    /**
     * Whether the customer has no other non-canceled orders
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function isNewCustomer(OrderInterface $order): bool
    {
        return $this->getStats($order)['count'] === 0;
    }

    /**
     * Sum of base grand totals minus refunds of all non-canceled orders of the customer, including this one
     *
     * @param OrderInterface $order
     * @return float
     */
    public function getLifetimeValue(OrderInterface $order): float
    {
        return round(
            $this->getStats($order)['value']
            + (float)$order->getBaseGrandTotal() - (float)$order->getBaseTotalRefunded(),
            2
        );
    }

    /**
     * Count and value of other orders
     *
     * @param OrderInterface $order
     * @return array{count: int, value: float}
     */
    private function getStats(OrderInterface $order): array
    {
        $key = (string)$order->getEntityId();
        if (isset($this->stats[$key])) {
            return $this->stats[$key];
        }
        $connection = $this->resource->getConnection('sales');
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('sales_order', 'sales'),
                [
                    'count' => new \Zend_Db_Expr('COUNT(*)'),
                    'value' => new \Zend_Db_Expr('SUM(base_grand_total - IFNULL(base_total_refunded, 0))'),
                ]
            )
            ->where('state <> ?', Order::STATE_CANCELED);
        if ($order->getEntityId()) {
            $select->where('entity_id <> ?', (int)$order->getEntityId());
        }
        if ($order->getCustomerId()) {
            $select->where('customer_id = ?', (int)$order->getCustomerId());
        } else {
            $select->where('customer_email = ?', (string)$order->getCustomerEmail());
        }
        $row = $connection->fetchRow($select) ?: [];

        return $this->stats[$key] = ['count' => (int)($row['count'] ?? 0), 'value' => (float)($row['value'] ?? 0)];
    }
}
