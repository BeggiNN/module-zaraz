<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Event\Builder;

use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Attribute\AttributeValueResolver;
use Perspective\Zaraz\Model\Event\Builder\ProductViewed;
use Perspective\Zaraz\Model\Event\Context;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\ProductLine;
use Perspective\Zaraz\Model\Mapper\ProductLoader;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\PriceResolver;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class ProductViewedTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * @return void
     */
    public function testPayloadAndPreloadedProductReuse(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(62);
        $store = $this->createMock(Store::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->with(1)->willReturn($store);

        $loader = $this->createMock(ProductLoader::class);
        $loader->expects($this->once())->method('register')->with($product, 1);
        $lineResolver = $this->createMock(LineResolver::class);
        $lineResolver->method('fromProduct')->with($product, $store)
            ->willReturn(new ProductLine(62, 'MH01', 'Hoodie', 52.0));
        $mapper = $this->createMock(ProductMapper::class);
        $mapper->method('map')->willReturn(['product_id' => 'MH01', 'price' => 52.0]);
        $priceResolver = $this->createMock(PriceResolver::class);
        $priceResolver->method('getStoreCurrency')->with($store)->willReturn('USD');

        $builder = new ProductViewed(
            $storeManager,
            $loader,
            $lineResolver,
            $mapper,
            $priceResolver,
            $this->createConfig([]),
            $this->createMock(AttributeValueResolver::class)
        );

        $this->assertSame('Product Viewed', $builder->getDefaultName());
        $this->assertSame(
            ['product_id' => 'MH01', 'price' => 52.0, 'currency' => 'USD', 'value' => 52.0],
            $builder->build(new Context(storeId: 1, product: $product))
        );
        $this->assertNull($builder->build(new Context(1)));
    }
}
