<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Cart;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Perspective\Zaraz\Model\Cart\CartChanges;
use PHPUnit\Framework\TestCase;

class CartChangesTest extends TestCase
{
    /**
     * Quote with stored data (orig data) and the given visible items
     *
     * @param Item[] $items
     * @param string|null $origCoupon
     * @param string|null $coupon
     * @return Quote
     */
    private function quote(array $items, ?string $origCoupon = null, ?string $coupon = null): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllVisibleItems', 'getId'])
            ->getMock();
        $quote->method('getAllVisibleItems')->willReturn($items);
        // The id field name is set by the constructor, which is disabled here
        $quote->method('getId')->willReturn(7);
        $quote->setData(['entity_id' => 7, 'coupon_code' => $origCoupon]);
        $quote->setOrigData();
        $quote->setData('coupon_code', $coupon);
        foreach ($items as $item) {
            $item->setData('quote_id', 7);
        }
        return $quote;
    }

    /**
     * Stored quote item with a new quantity
     *
     * @param int $id
     * @param float|null $origQty Null = new item
     * @param float $qty
     * @return Item
     */
    private function item(int $id, ?float $origQty, float $qty): Item
    {
        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote', 'getId'])
            ->getMock();
        $item->method('getQuote')->willReturn(null);
        $item->method('getId')->willReturnCallback(static fn () => $item->getData('item_id'));
        if ($origQty !== null) {
            $item->setData(['item_id' => $id, 'qty' => $origQty]);
            $item->setOrigData();
        }
        $item->setData('item_id', $origQty !== null ? $id : null);
        $item->setData('qty', $qty);
        return $item;
    }

    /**
     * @param array $entries
     * @return array
     */
    private function quantities(array $entries): array
    {
        return array_map(static fn (array $entry): float => $entry['qty'], $entries);
    }

    /**
     * @return void
     */
    public function testQuantityDifferences(): void
    {
        $increased = $this->item(1, 2.0, 5.0);
        $decreased = $this->item(2, 4.0, 1.0);
        $unchanged = $this->item(3, 1.0, 1.0);
        $quote = $this->quote([$increased, $decreased, $unchanged]);

        $changes = new CartChanges();
        $changes->collect($quote);
        $result = $changes->pull($quote);

        $this->assertSame([3.0], $this->quantities($result['added']));
        $this->assertSame([3.0], $this->quantities($result['removed']));
        $this->assertSame($decreased, $result['removed'][0]['item']);
        $this->assertNull($result['coupon']);
        $this->assertSame(['added' => [], 'removed' => [], 'coupon' => null], $changes->pull($quote), 'pulled once');
    }

    /**
     * @return void
     */
    public function testItemsAddedByAddProductAreNotCountedTwice(): void
    {
        $incremented = $this->item(1, 1.0, 3.0);
        $new = $this->item(2, null, 2.0);
        $quote = $this->quote([$incremented, $new]);

        $changes = new CartChanges();
        $changes->added($incremented, 2.0);
        $changes->added($new, 2.0);
        $changes->collect($quote);

        $this->assertSame([2.0, 2.0], $this->quantities($changes->pull($quote)['added']));
    }

    /**
     * @return void
     */
    public function testRemovedItemUsesQuantityBeforeRemoval(): void
    {
        $item = $this->item(1, 4.0, 4.0);
        $quote = $this->quote([$item]);

        $changes = new CartChanges();
        $changes->removed($item);
        $changes->collect($quote);
        $result = $changes->pull($quote);

        $this->assertSame([4.0], $this->quantities($result['removed']));
        $this->assertSame([], $result['added']);
    }

    /**
     * @return void
     */
    public function testMergedQuoteProducesNothing(): void
    {
        $quote = $this->quote([$this->item(1, 1.0, 3.0)], null, 'SALE');

        $changes = new CartChanges();
        $changes->merged($quote);
        $changes->collect($quote);

        $this->assertSame(['added' => [], 'removed' => [], 'coupon' => null], $changes->pull($quote));
    }

    /**
     * @return void
     */
    public function testCouponChanges(): void
    {
        $applied = $this->quote([], null, 'SALE');
        $replaced = $this->quote([], 'OLD', 'NEW');
        $changes = new CartChanges();
        $changes->collect($applied);
        $changes->collect($replaced);

        $this->assertSame(['applied' => 'SALE', 'removed' => ''], $changes->pull($applied)['coupon']);
        $this->assertSame(['applied' => 'NEW', 'removed' => 'OLD'], $changes->pull($replaced)['coupon']);
    }
}
