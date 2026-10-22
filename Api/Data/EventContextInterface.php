<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Input for event builders: the entity the event is about
 *
 * @api
 */
interface EventContextInterface
{
    /**
     * Store the event is generated for
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Product (Product Viewed)
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface;

    /**
     * Visible (parent) quote item (Product Added / Product Removed)
     *
     * @return CartItemInterface|null
     */
    public function getQuoteItem(): ?CartItemInterface;

    /**
     * Cart (Cart Viewed, Checkout Started, checkout steps)
     *
     * @return CartInterface|null
     */
    public function getQuote(): ?CartInterface;

    /**
     * Order (Order Completed)
     *
     * @return OrderInterface|null
     */
    public function getOrder(): ?OrderInterface;

    /**
     * Quantity the event is about (e.g. qty added), null = take it from the entity
     *
     * @return float|null
     */
    public function getQty(): ?float;

    /**
     * Additional data for third-party builders
     *
     * @param string $key
     * @return mixed
     */
    public function getData(string $key): mixed;
}
