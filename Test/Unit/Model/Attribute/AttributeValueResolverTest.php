<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Attribute;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Perspective\Zaraz\Model\Attribute\AttributeValueResolver;
use PHPUnit\Framework\TestCase;

class AttributeValueResolverTest extends TestCase
{
    /**
     * @return void
     */
    public function testOptionLabelsOfAListAreLoadedInOneQuery(): void
    {
        $source = $this->createMock(Table::class);
        $source->expects($this->once())
            ->method('getSpecificOptions')
            ->with(['5', '7', '9'], false)
            ->willReturn([
                ['value' => '5', 'label' => 'Wool'],
                ['value' => '7', 'label' => 'Nylon'],
                ['value' => '9', 'label' => ' Cotton '],
            ]);
        $source->expects($this->never())->method('getAllOptions');
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getId')->willReturn(1);
        $attribute->method('usesSource')->willReturn(true);
        $attribute->method('getSource')->willReturn($source);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($attribute);

        $products = [];
        foreach (['5,7', '9', null, '7'] as $value) {
            $product = $this->createMock(Product::class);
            $product->method('getData')->with('material')->willReturn($value);
            $products[] = $product;
        }
        $resolver = new AttributeValueResolver($eavConfig);
        $resolver->preload($products, ['material'], 1);

        $this->assertSame(
            ['Wool, Nylon', 'Cotton', null, 'Nylon'],
            array_map(static fn ($product) => $resolver->getValue($product, 'material', 1), $products)
        );
    }

    /**
     * @return void
     */
    public function testTextAttribute(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getId')->willReturn(1);
        $attribute->method('usesSource')->willReturn(false);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($attribute);
        $product = $this->createMock(Product::class);
        $product->method('getData')->willReturn(' Acme ');

        $this->assertSame('Acme', (new AttributeValueResolver($eavConfig))->getValue($product, 'brand_text', 1));
    }
}
