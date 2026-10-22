<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Event\Builder;

use Magento\Framework\Math\Random;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Api\OrderDataModifierInterface;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Event\Builder\OrderCompleted;
use Perspective\Zaraz\Model\Event\Context;
use Perspective\Zaraz\Model\Event\DeliveryId;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\Modifier\OrderDataModifierPool;
use Perspective\Zaraz\Model\Mapper\ProductLine;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\OrderTotals;
use Perspective\Zaraz\Model\Price\PriceResolver;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class OrderCompletedTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * @var ObjectManager
     */
    private ObjectManager $objectManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
    }

    /**
     * @param array $data
     * @return Order
     */
    private function createOrder(array $data = []): Order
    {
        $parent = $this->objectManager->getObject(OrderItem::class);
        $parent->setData(['item_id' => 1, 'sku' => 'MH01-XS-Black']);
        $child = $this->objectManager->getObject(OrderItem::class);
        $child->setData(['item_id' => 2, 'parent_item_id' => 1, 'sku' => 'MH01-XS-Black']);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getItems'])
            ->getMock();
        $order->method('getItems')->willReturn([$parent, $child]);
        $order->setData($data + [
            'store_id' => 1,
            'quote_id' => 24,
            'increment_id' => '000000011',
            'base_grand_total' => 652.4,
            'status' => 'pending',
            'coupon_code' => null,
        ]);
        return $order;
    }

    /**
     * @param array $settings
     * @param OrderDataModifierInterface[] $modifiers
     * @return OrderCompleted
     */
    private function createBuilder(array $settings = [], array $modifiers = []): OrderCompleted
    {
        $store = $this->createMock(Store::class);
        $store->method('getFrontendName')->willReturn('Main Website Store');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $lineResolver = $this->createMock(LineResolver::class);
        $lineResolver->expects($this->any())
            ->method('fromOrderItem')
            ->willReturn(new ProductLine(1, 'MH01', 'Hoodie', 52.0, 5.0));
        $mapper = $this->createMock(ProductMapper::class);
        $mapper->method('mapList')->willReturnCallback(
            static fn (array $lines): array => array_map(static fn (): array => ['product_id' => 'MH01'], $lines)
        );
        $totals = $this->createMock(OrderTotals::class);
        $totals->method('calculate')->willReturn(
            ['total' => 652.4, 'revenue' => 582.4, 'shipping' => 70.0, 'tax' => 0.0, 'discount' => 145.6]
        );
        $priceResolver = $this->createMock(PriceResolver::class);
        $priceResolver->method('getOrderCurrency')->willReturn('USD');

        return new OrderCompleted(
            $this->createConfig($settings),
            $storeManager,
            $lineResolver,
            $mapper,
            $priceResolver,
            $totals,
            new OrderDataModifierPool($modifiers),
            new DeliveryId($this->createMock(Random::class))
        );
    }

    /**
     * @return void
     */
    public function testPayloadContainsOnlyVisibleItemsAndSpecKeys(): void
    {
        $payload = $this->createBuilder()->build(new Context(storeId: 1, order: $this->createOrder()));

        $this->assertSame(
            [
                'checkout_id' => '24',
                'order_id' => '000000011',
                'event_id' => 'order-000000011',
                'affiliation' => 'Main Website Store',
                'total' => 652.4,
                'revenue' => 582.4,
                'shipping' => 70.0,
                'tax' => 0.0,
                'discount' => 145.6,
                'currency' => 'USD',
                'products' => [['product_id' => 'MH01']],
            ],
            $payload
        );
    }

    /**
     * @return void
     */
    public function testCouponAffiliationAndModifiers(): void
    {
        $modifier = $this->createMock(OrderDataModifierInterface::class);
        $modifier->method('modify')->willReturnCallback(
            static fn (array $data): array => $data + ['new_customer' => true]
        );
        $payload = $this->createBuilder([Config::XML_ORDER_AFFILIATION => 'Partner'], [$modifier])
            ->build(new Context(storeId: 1, order: $this->createOrder(['coupon_code' => 'SUMMER'])));

        $this->assertSame('SUMMER', $payload['coupon']);
        $this->assertSame('Partner', $payload['affiliation']);
        $this->assertTrue($payload['new_customer']);
    }

    /**
     * @return void
     */
    public function testZeroTotalAndExcludedStatuses(): void
    {
        $zero = new Context(storeId: 1, order: $this->createOrder(['base_grand_total' => 0]));
        $this->assertNull($this->createBuilder()->build($zero));
        $this->assertNotNull($this->createBuilder([Config::XML_ORDER_SKIP_ZERO => '0'])->build($zero));

        $fraud = new Context(storeId: 1, order: $this->createOrder(['status' => 'fraud']));
        $this->assertNull($this->createBuilder([Config::XML_ORDER_SKIP_STATUSES => 'fraud,holded'])->build($fraud));
        $this->assertNotNull($this->createBuilder()->build($fraud));
    }

    /**
     * @return void
     */
    public function testNoOrderInContext(): void
    {
        $this->assertNull($this->createBuilder()->build(new Context(1)));
    }
}
