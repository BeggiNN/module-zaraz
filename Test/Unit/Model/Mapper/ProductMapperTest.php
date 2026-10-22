<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Mapper;

use Magento\Catalog\Model\Product;
use Perspective\Zaraz\Api\ProductDataModifierInterface;
use Perspective\Zaraz\Model\Category\CategoryResolver;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Mapper\Modifier\ProductDataModifierPool;
use Perspective\Zaraz\Model\Mapper\ProductLine;
use Perspective\Zaraz\Model\Mapper\ProductLoader;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductMapperTest extends TestCase
{
    use ConfigStubTrait;

    private const PARENT_ID = 62;
    private const CHILD_ID = 101;

    /**
     * Configurable line as it comes from a quote/order item: item SKU is the child SKU
     *
     * @return ProductLine
     */
    private function configurableLine(): ProductLine
    {
        return new ProductLine(
            productId: self::PARENT_ID,
            sku: 'MH01-XS-Black',
            name: 'Chaz Kangeroo Hoodie',
            price: 52.0,
            qty: 2.0,
            childId: self::CHILD_ID,
            childSku: 'MH01-XS-Black',
            childName: 'Chaz Kangeroo Hoodie-XS-Black',
            options: [['label' => 'Size', 'value' => 'XS'], ['label' => 'Color', 'value' => 'Black']],
        );
    }

    /**
     * @param array $settings
     * @param ProductDataModifierInterface[] $modifiers
     * @param bool $productExists
     * @return ProductMapper
     */
    private function createMapper(array $settings, array $modifiers = [], bool $productExists = true): ProductMapper
    {
        $parent = $this->createMock(Product::class);
        $parent->method('getData')->willReturnMap([['sku', null, 'MH01']]);
        $parent->method('getName')->willReturn('Chaz Kangeroo Hoodie');
        $child = $this->createMock(Product::class);

        $loader = $this->createMock(ProductLoader::class);
        $loader->method('load')->willReturn(
            $productExists ? [self::PARENT_ID => $parent, self::CHILD_ID => $child] : []
        );
        $categoryResolver = $this->createMock(CategoryResolver::class);
        $categoryResolver->method('getCategoryData')->with($parent, 1)->willReturn(['category' => 'Men/Tops/Hoodies']);

        return new ProductMapper(
            $this->createConfig($settings),
            $loader,
            $categoryResolver,
            new ProductDataModifierPool($modifiers)
        );
    }

    /**
     * @return array
     */
    public static function identityProvider(): array
    {
        return [
            'defaults: parent SKU as id, sku sent, option values as variant' => [
                [],
                [
                    'product_id' => 'MH01',
                    'sku' => 'MH01',
                    'name' => 'Chaz Kangeroo Hoodie',
                    'variant' => 'XS / Black',
                ],
            ],
            'entity id without sku' => [
                [Config::XML_PRODUCT_ID_SOURCE => Config::ID_SOURCE_ENTITY, Config::XML_PRODUCT_SEND_SKU => '0'],
                ['product_id' => '62', 'name' => 'Chaz Kangeroo Hoodie', 'variant' => 'XS / Black'],
            ],
            'child identity' => [
                [Config::XML_CONFIGURABLE_MODE => Config::CONFIGURABLE_CHILD],
                [
                    'product_id' => 'MH01-XS-Black',
                    'sku' => 'MH01-XS-Black',
                    'name' => 'Chaz Kangeroo Hoodie-XS-Black',
                    'variant' => 'XS / Black',
                ],
            ],
            'child entity id' => [
                [
                    Config::XML_CONFIGURABLE_MODE => Config::CONFIGURABLE_CHILD,
                    Config::XML_PRODUCT_ID_SOURCE => Config::ID_SOURCE_ENTITY,
                ],
                [
                    'product_id' => '101',
                    'sku' => 'MH01-XS-Black',
                    'name' => 'Chaz Kangeroo Hoodie-XS-Black',
                    'variant' => 'XS / Black',
                ],
            ],
            'variant from child sku' => [
                [Config::XML_VARIANT_SOURCE => Config::VARIANT_CHILD_SKU],
                [
                    'product_id' => 'MH01',
                    'sku' => 'MH01',
                    'name' => 'Chaz Kangeroo Hoodie',
                    'variant' => 'MH01-XS-Black',
                ],
            ],
            'variant from child name' => [
                [Config::XML_VARIANT_SOURCE => Config::VARIANT_CHILD_NAME],
                [
                    'product_id' => 'MH01',
                    'sku' => 'MH01',
                    'name' => 'Chaz Kangeroo Hoodie',
                    'variant' => 'Chaz Kangeroo Hoodie-XS-Black',
                ],
            ],
            'variant disabled' => [
                [Config::XML_VARIANT_ENABLED => '0'],
                ['product_id' => 'MH01', 'sku' => 'MH01', 'name' => 'Chaz Kangeroo Hoodie'],
            ],
        ];
    }

    /**
     * @param array $settings
     * @param array $expectedIdentity
     * @return void
     */
    #[DataProvider('identityProvider')]
    public function testIdentityAndVariant(array $settings, array $expectedIdentity): void
    {
        $data = $this->createMapper($settings)->map($this->configurableLine(), 1);

        $this->assertEquals(
            $expectedIdentity + ['category' => 'Men/Tops/Hoodies', 'price' => 52.0, 'quantity' => 2.0],
            $data
        );
    }

    /**
     * @return void
     */
    public function testModifiersReceiveParentAndChild(): void
    {
        $modifier = $this->createMock(ProductDataModifierInterface::class);
        $modifier->expects($this->once())
            ->method('modify')
            ->with($this->isType('array'), $this->isInstanceOf(Product::class), $this->isInstanceOf(Product::class), 1)
            ->willReturnCallback(static fn (array $data): array => $data + ['stock_status' => 'in_stock']);

        $data = $this->createMapper([], [$modifier])->map($this->configurableLine(), 1);

        $this->assertSame('in_stock', $data['stock_status']);
    }

    /**
     * @return void
     */
    public function testDeletedProductFallsBackToLineDataWithoutModifiers(): void
    {
        $modifier = $this->createMock(ProductDataModifierInterface::class);
        $modifier->expects($this->never())->method('modify');

        $data = $this->createMapper([], [$modifier], false)->map(
            new ProductLine(productId: 5, sku: 'OLD-1', name: 'Removed product', price: 10.0, qty: 1.0),
            1
        );

        $this->assertEquals(
            [
                'product_id' => 'OLD-1',
                'sku' => 'OLD-1',
                'name' => 'Removed product',
                'price' => 10.0,
                'quantity' => 1.0,
            ],
            $data
        );
    }

    /**
     * @return void
     */
    public function testSimpleProductHasNoVariantAndNoQuantityWhenQtyUnknown(): void
    {
        $data = $this->createMapper([])->map(
            new ProductLine(productId: self::PARENT_ID, sku: 'MH01', name: 'Chaz Kangeroo Hoodie', price: 52.0),
            1
        );

        $this->assertArrayNotHasKey('variant', $data);
        $this->assertArrayNotHasKey('quantity', $data);
    }
}
