<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Price;

use Perspective\Zaraz\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;

/**
 * Unit prices and currency according to "Prices and totals" settings
 */
class PriceResolver
{
    /**
     * @param Config $config
     * @param CatalogHelper $catalogHelper
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        private readonly Config $config,
        private readonly CatalogHelper $catalogHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
    ) {
    }

    /**
     * Catalog final price of a product for the current customer context
     *
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return float
     */
    public function getProductPrice(ProductInterface $product, StoreInterface $store): float
    {
        $storeId = (int)$store->getId();
        // Amount base = price without tax adjustment, in base currency
        $price = (float)$product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount();
        if ($this->config->isProductPriceInclTax($storeId)) {
            $price = (float)$this->catalogHelper->getTaxPrice($product, $price, true, null, null, null, $store, false);
        }
        if (!$this->config->isBaseCurrency($storeId)) {
            $price = $this->priceCurrency->convert($price, $store);
        }
        return $this->round($price);
    }

    /**
     * Currency code of catalog prices for the store
     *
     * @param StoreInterface $store
     * @return string
     */
    public function getStoreCurrency(StoreInterface $store): string
    {
        /** @var Store $store */
        return $this->config->isBaseCurrency((int)$store->getId())
            ? (string)$store->getBaseCurrencyCode()
            : (string)$store->getCurrentCurrencyCode();
    }

    /**
     * Unit price of a quote item
     *
     * Quote item "price" is stored in base currency, so row totals are used for both currencies. When totals were
     * not collected (programmatic add without collectTotals), the product final price is used instead.
     *
     * @param QuoteItem $item
     * @return float
     */
    public function getQuoteItemPrice(QuoteItem $item): float
    {
        $storeId = (int)$item->getStoreId();
        $qty = (float)$item->getQty() ?: 1.0;
        $inclTax = $this->config->isProductPriceInclTax($storeId);
        $base = $this->config->isBaseCurrency($storeId);
        if ($base) {
            $row = $inclTax ? $item->getBaseRowTotalInclTax() : $item->getBaseRowTotal();
        } else {
            $row = $inclTax ? $item->getRowTotalInclTax() : $item->getRowTotal();
        }
        if ($row !== null && $row !== '') {
            return $this->round((float)$row / $qty);
        }

        $product = $item->getProduct();
        if (!$product) {
            return 0.0;
        }
        $store = $item->getQuote()?->getStore();
        $price = (float)$product->getFinalPrice($qty);
        if ($inclTax) {
            $price = (float)$this->catalogHelper->getTaxPrice($product, $price, true, null, null, null, $store, false);
        }
        if (!$base) {
            $price = $this->priceCurrency->convert($price, $store);
        }
        return $this->round($price);
    }

    /**
     * Currency code of quote amounts
     *
     * @param QuoteItem $item
     * @return string
     */
    public function getQuoteCurrency(QuoteItem $item): string
    {
        $quote = $item->getQuote();
        return $this->config->isBaseCurrency((int)$item->getStoreId())
            ? (string)$quote->getBaseCurrencyCode()
            : (string)$quote->getQuoteCurrencyCode();
    }

    /**
     * Unit price of an order item
     *
     * @param OrderItemInterface $item
     * @return float
     */
    public function getOrderItemPrice(OrderItemInterface $item): float
    {
        $storeId = (int)$item->getStoreId();
        $inclTax = $this->config->isProductPriceInclTax($storeId);
        if ($this->config->isBaseCurrency($storeId)) {
            $price = $inclTax ? $item->getBasePriceInclTax() : $item->getBasePrice();
        } else {
            $price = $inclTax ? $item->getPriceInclTax() : $item->getPrice();
        }
        return $this->round((float)$price);
    }

    /**
     * Currency code of order amounts
     *
     * @param OrderInterface $order
     * @return string
     */
    public function getOrderCurrency(OrderInterface $order): string
    {
        return $this->config->isBaseCurrency((int)$order->getStoreId())
            ? (string)$order->getBaseCurrencyCode()
            : (string)$order->getOrderCurrencyCode();
    }

    /**
     * Round an amount for the payload
     *
     * @param float $amount
     * @return float
     */
    public function round(float $amount): float
    {
        return round($amount, 2);
    }
}
