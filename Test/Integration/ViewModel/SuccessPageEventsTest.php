<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Integration\ViewModel;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Perspective\Zaraz\Model\Page\SuccessPageContext;
use Perspective\Zaraz\ViewModel\PageEvents;
use PHPUnit\Framework\TestCase;

/**
 * Order Completed on the success page is rendered once per order
 */
#[AppArea('frontend')]
#[DbIsolation(true)]
#[DataFixture(ProductFixture::class, ['sku' => 'zaraz-order', 'price' => 10], 'product')]
#[DataFixture(GuestCartFixture::class, as: 'cart')]
#[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 2])]
#[DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order')]
class SuccessPageEventsTest extends TestCase
{
    /**
     * Render the success page events block data for the order
     *
     * @param OrderInterface $order
     * @return string|null
     */
    private function render(OrderInterface $order): ?string
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(CheckoutSession::class)->setLastRealOrderId($order->getIncrementId());

        return $objectManager->get(PageEvents::class)->getEventsJson(
            $objectManager->create(SuccessPageContext::class),
            ['order_completed' => 'order_completed']
        );
    }

    /**
     * @return void
     */
    public function testOrderCompletedIsRenderedOnceWithStableId(): void
    {
        /** @var OrderInterface $order */
        $order = DataFixtureStorageManager::getStorage()->get('order');

        $events = json_decode((string)$this->render($order), true);

        $this->assertCount(1, $events);
        $this->assertSame('Order Completed', $events[0]['name']);
        $this->assertSame('order-' . $order->getIncrementId(), $events[0]['id']);
        $payload = $events[0]['payload'];
        $this->assertSame($order->getIncrementId(), $payload['order_id']);
        $this->assertSame('order-' . $order->getIncrementId(), $payload['event_id'], 'Meta / TikTok deduplication id');
        $this->assertEquals(round((float)$order->getGrandTotal(), 2), $payload['total']);
        $this->assertEquals(20.0, $payload['revenue']);
        $this->assertSame('USD', $payload['currency']);
        $this->assertCount(1, $payload['products']);
        $this->assertSame('zaraz-order', $payload['products'][0]['product_id']);
        $this->assertEquals(2.0, $payload['products'][0]['quantity']);

        $this->assertNull($this->render($order), 'reload of the success page must not render the order again');
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/order/skip_statuses', 'pending', 'store', 'default')]
    public function testExcludedStatusIsNotRendered(): void
    {
        $this->assertNull($this->render(DataFixtureStorageManager::getStorage()->get('order')));
    }
}
