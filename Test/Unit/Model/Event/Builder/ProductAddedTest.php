<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Event\Builder;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Perspective\Zaraz\Model\Event\Builder\ProductAdded;
use Perspective\Zaraz\Model\Event\Context;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\ProductLine;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\PriceResolver;
use PHPUnit\Framework\TestCase;

class ProductAddedTest extends TestCase
{
    /**
     * @param string|null $coupon
     * @return QuoteItem
     */
    private function createItem(?string $coupon = null): QuoteItem
    {
        $quote = (new ObjectManager($this))->getObject(Quote::class);
        $quote->setData('coupon_code', $coupon);
        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote'])
            ->getMock();
        $item->method('getQuote')->willReturn($quote);
        $item->setData(['qty' => 5, 'store_id' => 1]);
        return $item;
    }

    /**
     * @param float|null $expectedQty
     * @return ProductAdded
     */
    private function createBuilder(?float $expectedQty = null): ProductAdded
    {
        $lineResolver = $this->createMock(LineResolver::class);
        $lineResolver->method('fromQuoteItem')->willReturnCallback(
            function (QuoteItem $item, ?float $qty) use ($expectedQty): ProductLine {
                if ($expectedQty !== null) {
                    $this->assertSame($expectedQty, $qty);
                }
                return new ProductLine(1, 'MH01', 'Hoodie', 19.99, $qty);
            }
        );
        $mapper = $this->createMock(ProductMapper::class);
        $mapper->method('map')->willReturnCallback(
            static fn (ProductLine $line): array => [
                'product_id' => 'MH01',
                'price' => $line->price,
                'quantity' => $line->qty,
            ]
        );
        $priceResolver = $this->createMock(PriceResolver::class);
        $priceResolver->method('getQuoteCurrency')->willReturn('USD');
        $priceResolver->method('round')->willReturnCallback(static fn (float $v): float => round($v, 2));

        return new ProductAdded($lineResolver, $mapper, $priceResolver);
    }

    /**
     * Quantity is the added difference from the context, not the item qty
     *
     * @return void
     */
    public function testAddedQuantityAndValue(): void
    {
        $payload = $this->createBuilder(3.0)->build(new Context(storeId: 1, quoteItem: $this->createItem(), qty: 3.0));

        $this->assertSame(
            ['product_id' => 'MH01', 'price' => 19.99, 'quantity' => 3.0, 'currency' => 'USD', 'value' => 59.97],
            $payload
        );
    }

    /**
     * @return void
     */
    public function testItemQtyIsUsedWithoutContextQtyAndCouponIsAdded(): void
    {
        $payload = $this->createBuilder(5.0)->build(new Context(storeId: 1, quoteItem: $this->createItem('SALE')));

        $this->assertSame(5.0, $payload['quantity']);
        $this->assertSame('SALE', $payload['coupon']);
    }

    /**
     * @return void
     */
    public function testSkipped(): void
    {
        $builder = $this->createBuilder();
        $this->assertNull($builder->build(new Context(1)));
        $this->assertNull($builder->build(new Context(storeId: 1, quoteItem: $this->createItem(), qty: 0.0)));
    }
}
