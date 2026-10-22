<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Integration\CustomerData;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Perspective\Zaraz\CustomerData\EventsSection;
use PHPUnit\Framework\TestCase;

/**
 * Frontend add-to-cart flow (Checkout\Model\Cart, as the cart/add controller) → "perspective-zaraz" section
 */
#[AppArea('frontend')]
#[DbIsolation(true)]
class EventsSectionTest extends TestCase
{
    /**
     * Start every test with an empty checkout session (quotes of previous tests are rolled back)
     *
     * Data fixtures are applied before setUp(), so the event queue must not be touched here.
     *
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(CheckoutSession::class)->clearStorage();
        $objectManager->removeSharedInstance(CheckoutSession::class);
        $objectManager->removeSharedInstance(Cart::class);
    }

    /**
     * Leave an empty event queue for the next test
     *
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->readSection();
    }

    /**
     * Add to cart through the frontend cart model
     *
     * @param int $productId
     * @param int $qty
     * @return void
     */
    private function addToCart(int $productId, int $qty): void
    {
        $cart = Bootstrap::getObjectManager()->get(Cart::class);
        $cart->addProduct($productId, ['qty' => $qty]);
        $cart->save();
    }

    /**
     * @return array
     */
    private function readSection(): array
    {
        return Bootstrap::getObjectManager()->get(EventsSection::class)->getSectionData();
    }

    /**
     * @return void
     */
    #[DataFixture(ProductFixture::class, ['sku' => 'zaraz-simple', 'name' => 'Zaraz Simple', 'price' => 10], 'product')]
    public function testProductAddedIsDeliveredOnceWithAddedQuantity(): void
    {
        $productId = (int)DataFixtureStorageManager::getStorage()->get('product')->getId();

        $this->addToCart($productId, 2);
        $events = $this->readSection()['events'];

        $this->assertCount(1, $events);
        $this->assertSame('ecommerce', $events[0]['method']);
        $this->assertSame('Product Added', $events[0]['name']);
        $this->assertStringStartsWith('product_added-', $events[0]['id']);
        $payload = (array)$events[0]['payload'];
        $this->assertSame('zaraz-simple', $payload['product_id']);
        $this->assertSame('Zaraz Simple', $payload['name']);
        $this->assertEquals(10.0, $payload['price']);
        $this->assertEquals(2.0, $payload['quantity']);
        $this->assertEquals(20.0, $payload['value']);
        $this->assertSame('USD', $payload['currency']);

        $this->assertSame([], $this->readSection()['events'], 'section is read-once: a reload gets nothing');

        $this->addToCart($productId, 1);
        $events = $this->readSection()['events'];
        $this->assertCount(1, $events);
        $this->assertEquals(1.0, ((array)$events[0]['payload'])['quantity'], 'quantity is the added difference');
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/general/enabled', '0', 'store', 'default')]
    #[DataFixture(ProductFixture::class, ['price' => 10], 'product')]
    #[DataFixture(GuestCartFixture::class, as: 'cart')]
    #[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$'])]
    public function testDisabledModuleQueuesNothing(): void
    {
        $this->assertSame(['events' => [], 'user' => [], 'debug' => false], $this->readSection());
    }

    /**
     * @return void
     */
    #[Config(
        'perspective_zaraz/events/events',
        '{"_1":{"event":"product_added","enabled":"0","browser":"1"}}',
        'store',
        'default'
    )]
    #[DataFixture(ProductFixture::class, ['price' => 10], 'product')]
    #[DataFixture(GuestCartFixture::class, as: 'cart')]
    #[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$'])]
    public function testDisabledEventIsNotQueued(): void
    {
        $this->assertSame([], $this->readSection()['events']);
    }

    /**
     * Control for the two tests above: the same fixture flow queues the event when it is enabled
     *
     * @return void
     */
    #[DataFixture(ProductFixture::class, ['price' => 10], 'product')]
    #[DataFixture(GuestCartFixture::class, as: 'cart')]
    #[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$'])]
    public function testFixtureFlowQueuesEventWhenEnabled(): void
    {
        $this->assertCount(1, $this->readSection()['events']);
    }
}
