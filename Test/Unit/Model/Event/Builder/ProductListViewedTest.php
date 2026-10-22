<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Event\Builder;

use Magento\Catalog\Model\Product;
use Perspective\Zaraz\Model\Event\Builder\ProductListViewed;
use Perspective\Zaraz\Model\Event\Context;
use Perspective\Zaraz\Model\Event\Payload\ProductPayload;
use PHPUnit\Framework\TestCase;

class ProductListViewedTest extends TestCase
{
    /**
     * @return void
     */
    public function testChunkPositionsAndListFields(): void
    {
        $products = [$this->createMock(Product::class), $this->createMock(Product::class)];
        $payload = $this->createMock(ProductPayload::class);
        $payload->method('buildList')->with($products, 1)->willReturn([
            ['product_id' => 'A', 'price' => 1.0],
            ['product_id' => 'B', 'price' => 2.0],
        ]);
        $payload->method('getCurrency')->willReturn('USD');

        $result = (new ProductListViewed($payload))->build(new Context(storeId: 1, data: [
            'products' => $products,
            'list_id' => 'category_14',
            'list_name' => 'Jackets',
            'offset' => 20,
        ]));

        $this->assertSame(
            [
                'list_id' => 'category_14',
                'category' => 'Jackets',
                'currency' => 'USD',
                'products' => [
                    ['product_id' => 'A', 'price' => 1.0, 'position' => 21, 'list_name' => 'Jackets'],
                    ['product_id' => 'B', 'price' => 2.0, 'position' => 22, 'list_name' => 'Jackets'],
                ],
            ],
            $result
        );
        $this->assertNull((new ProductListViewed($payload))->build(new Context(1)));
    }
}
