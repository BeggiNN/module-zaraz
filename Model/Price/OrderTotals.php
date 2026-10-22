<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Price;

use Perspective\Zaraz\Model\Config;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Order-level amounts of Order Completed / a credit memo of Order Refunded according to "Prices and totals"
 *
 * S = subtotal, D = discount (negative), Sh = shipping, T = tax (incl. shipping tax), Ts = shipping tax:
 *  - grand_total: total = grand total − T (exclude tax) or − Ts (exclude shipping tax) − Sh (exclude shipping)
 *  - subtotal:    total = S + D + (T − Ts unless tax is excluded); shipping never included
 *  - revenue = S + D; shipping = Sh (+ Ts when "shipping incl. tax"); tax = T; discount = |D|
 */
class OrderTotals
{
    /**
     * @param Config $config
     * @param PriceResolver $priceResolver
     */
    public function __construct(
        private readonly Config $config,
        private readonly PriceResolver $priceResolver,
    ) {
    }

    /**
     * Amounts in the configured currency
     *
     * @param OrderInterface|CreditmemoInterface $order credit memos have the same total getters
     * @return array{total: float, revenue: float, shipping: float, tax: float, discount: float}
     */
    public function calculate(OrderInterface|CreditmemoInterface $order): array
    {
        $storeId = (int)$order->getStoreId();
        $base = $this->config->isBaseCurrency($storeId);

        $grandTotal = (float)($base ? $order->getBaseGrandTotal() : $order->getGrandTotal());
        $subtotal = (float)($base ? $order->getBaseSubtotal() : $order->getSubtotal());
        $discount = (float)($base ? $order->getBaseDiscountAmount() : $order->getDiscountAmount());
        $shipping = (float)($base ? $order->getBaseShippingAmount() : $order->getShippingAmount());
        $tax = (float)($base ? $order->getBaseTaxAmount() : $order->getTaxAmount());
        $shippingTax = (float)($base ? $order->getBaseShippingTaxAmount() : $order->getShippingTaxAmount());

        $excludeTax = $this->config->isExcludeTax($storeId);
        if ($this->config->getOrderTotalSource($storeId) === Config::TOTAL_SUBTOTAL) {
            $total = $subtotal + $discount + ($excludeTax ? 0.0 : $tax - $shippingTax);
        } else {
            $total = $grandTotal;
            if ($excludeTax) {
                $total -= $tax;
            } elseif ($this->config->isExcludeShippingTax($storeId)) {
                $total -= $shippingTax;
            }
            if ($this->config->isExcludeShipping($storeId)) {
                $total -= $shipping;
            }
        }

        return [
            'total' => $this->priceResolver->round(max(0.0, $total)),
            'revenue' => $this->priceResolver->round(max(0.0, $subtotal + $discount)),
            'shipping' => $this->priceResolver->round(
                $shipping + ($this->config->isShippingInclTax($storeId) ? $shippingTax : 0.0)
            ),
            'tax' => $this->priceResolver->round($tax),
            'discount' => $this->priceResolver->round(abs($discount)),
        ];
    }
}
