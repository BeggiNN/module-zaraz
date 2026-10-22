<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Event builder input
 */
class Context implements EventContextInterface
{
    /**
     * @param int $storeId
     * @param ProductInterface|null $product
     * @param CartItemInterface|null $quoteItem
     * @param OrderInterface|null $order
     * @param float|null $qty
     * @param array $data
     * @param CartInterface|null $quote
     */
    public function __construct(
        private readonly int $storeId,
        private readonly ?ProductInterface $product = null,
        private readonly ?CartItemInterface $quoteItem = null,
        private readonly ?OrderInterface $order = null,
        private readonly ?float $qty = null,
        private readonly array $data = [],
        private readonly ?CartInterface $quote = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getQuote(): ?CartInterface
    {
        return $this->quote;
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * @inheritDoc
     */
    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    /**
     * @inheritDoc
     */
    public function getQuoteItem(): ?CartItemInterface
    {
        return $this->quoteItem;
    }

    /**
     * @inheritDoc
     */
    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    /**
     * @inheritDoc
     */
    public function getQty(): ?float
    {
        return $this->qty;
    }

    /**
     * @inheritDoc
     */
    public function getData(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
