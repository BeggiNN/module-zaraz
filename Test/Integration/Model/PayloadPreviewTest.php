<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Integration\Model;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Perspective\Zaraz\Model\Preview\PayloadPreview;
use PHPUnit\Framework\TestCase;

/**
 * Preview payload service (admin page backend)
 */
#[AppArea('adminhtml')]
#[DbIsolation(true)]
class PayloadPreviewTest extends TestCase
{
    /**
     * @return PayloadPreview
     */
    private function preview(): PayloadPreview
    {
        return Bootstrap::getObjectManager()->get(PayloadPreview::class);
    }

    /**
     * @return void
     */
    #[DataFixture(ProductFixture::class, ['sku' => 'zaraz-preview', 'price' => 12], 'product')]
    public function testProductBySkuAndId(): void
    {
        $bySku = $this->preview()->preview('product', 'zaraz-preview', 1);
        $byId = $this->preview()->preview(
            'product',
            (string)DataFixtureStorageManager::getStorage()->get('product')->getId(),
            1
        );

        $this->assertSame('Product Viewed', $bySku['product_viewed']['event']['name']);
        $this->assertEquals(12.0, ((array)$bySku['product_viewed']['event']['payload'])['price']);
        $this->assertSame(array_keys($bySku), array_keys($byId));
        $this->assertArrayHasKey('product_added_to_compare', $bySku);
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/ads/enabled', '1', 'store', 'default')]
    #[DataFixture(ProductFixture::class, ['price' => 10], 'product')]
    #[DataFixture(GuestCartFixture::class, as: 'cart')]
    #[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$'])]
    #[DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order')]
    public function testOrderAndCartWithoutSideEffects(): void
    {
        $order = DataFixtureStorageManager::getStorage()->get('order');
        $result = $this->preview()->preview('order', (string)$order->getIncrementId(), 1);
        $payload = (array)$result['order_completed']['event']['payload'];
        $this->assertSame($order->getIncrementId(), $payload['order_id']);
        $this->assertTrue($payload['new_customer'], 'Google Ads data is added when enabled');

        $again = $this->preview()->preview('order', (string)$order->getIncrementId(), 1);
        $this->assertArrayHasKey('event', $again['order_completed'], 'no "rendered once" flag is set by preview');

        $cart = $this->preview()->preview('cart', (string)$order->getQuoteId(), 1);
        $this->assertSame('Cart Viewed', $cart['cart_viewed']['event']['name']);
        $this->assertArrayHasKey('shipping_info_entered', $cart);
    }

    /**
     * @return void
     */
    public function testUnknownEntity(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->preview()->preview('order', 'NO-SUCH-ORDER', 1);
    }
}
