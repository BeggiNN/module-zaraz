<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Cart;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;

class CartChanges
{
    /**
     * @var array<int, array{item: AbstractItem, qty: float}>
     */
    private array $added = [];

    /**
     * @var array<int, array{item: AbstractItem, qty: float}>
     */
    private array $changed = [];

    /**
     * @var array<int, array{item: AbstractItem, qty: float}>
     */
    private array $removed = [];

    /**
     * @var array<int, array{applied: string, removed: string}>
     */
    private array $coupons = [];

    /**
     * @var array<int, true>
     */
    private array $merged = [];

    /**
     * Quantity added to a visible item by addProduct
     *
     * @param AbstractItem $item
     * @param float $qty
     * @return void
     */
    public function added(AbstractItem $item, float $qty): void
    {
        $key = spl_object_id($item);
        $this->added[$key] = ['item' => $item, 'qty' => ($this->added[$key]['qty'] ?? 0.0) + $qty];
    }

    /**
     * Removed visible item with its quantity before removal
     *
     * @param AbstractItem $item
     * @return void
     */
    public function removed(AbstractItem $item): void
    {
        $qty = (float)($item->getOrigData('qty') ?? $item->getQty());
        if ($qty > 0) {
            $this->removed[spl_object_id($item)] = ['item' => $item, 'qty' => $qty];
        }
    }

    /**
     * The quote was merged (login): its changes are not customer actions
     *
     * @param Quote $quote
     * @return void
     */
    public function merged(Quote $quote): void
    {
        $this->merged[spl_object_id($quote)] = true;
    }

    /**
     * Collect qty differences and coupon change before the quote is saved
     *
     * @param Quote $quote
     * @return void
     */
    public function collect(Quote $quote): void
    {
        if (isset($this->merged[spl_object_id($quote)])) {
            return;
        }
        foreach ($quote->getAllVisibleItems() as $item) {
            $key = spl_object_id($item);
            if (!$item->getId() || $item->isDeleted() || isset($this->added[$key]) || isset($this->removed[$key])) {
                continue;
            }
            $orig = $item->getOrigData('qty');
            if ($orig === null) {
                continue;
            }
            $diff = (float)$item->getQty() - (float)$orig;
            if (abs($diff) > 0.0001) {
                $this->changed[$key] = ['item' => $item, 'qty' => $diff];
            }
        }

        if ($quote->getId() && $quote->getOrigData('entity_id')) {
            $before = (string)$quote->getOrigData('coupon_code');
            $after = (string)$quote->getCouponCode();
            if ($before !== $after) {
                $this->coupons[spl_object_id($quote)] = ['applied' => $after, 'removed' => $before];
            }
        }
    }

    /**
     * Return and forget all changes of the quote
     *
     * @param Quote $quote
     * @return array{added: array, removed: array, coupon: array{applied: string, removed: string}|null}
     */
    public function pull(Quote $quote): array
    {
        $quoteKey = spl_object_id($quote);
        $result = ['added' => [], 'removed' => [], 'coupon' => $this->coupons[$quoteKey] ?? null];
        unset($this->coupons[$quoteKey], $this->merged[$quoteKey]);

        $lists = ['added' => $this->added, 'removed' => $this->removed, 'changed' => $this->changed];
        foreach ($lists as $type => $list) {
            foreach ($list as $key => $entry) {
                if ($entry['item']->getQuote() !== $quote && $entry['item']->getQuoteId() != $quote->getId()) {
                    continue;
                }
                unset($this->{$type}[$key]);
                if ($type === 'changed') {
                    $target = $entry['qty'] > 0 ? 'added' : 'removed';
                    $result[$target][] = ['item' => $entry['item'], 'qty' => abs($entry['qty'])];
                } else {
                    $result[$type][] = $entry;
                }
            }
        }
        $this->syncStoredData($quote);
        return $result;
    }

    /**
     * Treat the saved state as the new baseline, so a second save in the same request reports nothing twice
     *
     * @param Quote $quote
     * @return void
     */
    private function syncStoredData(Quote $quote): void
    {
        // Items are saved after sales_quote_save_after, so new items have no id yet; their baseline is set anyway
        foreach ($quote->getAllVisibleItems() as $item) {
            if (!$item->isDeleted()) {
                $item->setOrigData('qty', $item->getQty());
            }
        }
        if ($quote->getId()) {
            $quote->setOrigData('entity_id', $quote->getId());
            $quote->setOrigData('coupon_code', $quote->getCouponCode());
        }
    }
}
