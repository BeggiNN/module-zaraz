<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Integration\CustomerData;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\SalesRule\Test\Fixture\Rule as RuleFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Perspective\Zaraz\CustomerData\EventsSection;
use PHPUnit\Framework\TestCase;

/**
 * Cart page actions (Checkout\Model\Cart, as the cart controllers) → queued events
 */
#[AppArea('frontend')]
#[DbIsolation(true)]
class CartChangesTest extends TestCase
{
    /**
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
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->events();
    }

    /**
     * Queued events as [name, quantity|coupon]
     *
     * @return array
     */
    private function events(): array
    {
        $result = [];
        foreach (Bootstrap::getObjectManager()->get(EventsSection::class)->getSectionData()['events'] as $event) {
            $payload = (array)$event['payload'];
            $result[] = [$event['name'], $payload['quantity'] ?? $payload['coupon'] ?? null];
        }
        return $result;
    }

    /**
     * Save the cart as a new request would: totals are collected again
     *
     * @param Cart $cart
     * @return void
     */
    private function saveCart(Cart $cart): void
    {
        $cart->getQuote()->setTotalsCollectedFlag(false);
        $cart->save();
        $this->nextRequest();
    }

    /**
     * Start a "new request": the quote is loaded again from the database (session storage is kept)
     *
     * @return void
     */
    private function nextRequest(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->removeSharedInstance(CheckoutSession::class);
        $objectManager->removeSharedInstance(Cart::class);
    }

    /**
     * Apply a coupon as the checkout/cart/couponPost controller does
     *
     * @param string $code
     * @return void
     */
    private function applyCoupon(string $code): void
    {
        $quote = $this->cart()->getQuote();
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setTotalsCollectedFlag(false)->setCouponCode($code)->collectTotals();
        Bootstrap::getObjectManager()->get(CartRepositoryInterface::class)->save($quote);
        $this->nextRequest();
    }

    /**
     * @return Cart
     */
    private function cart(): Cart
    {
        return Bootstrap::getObjectManager()->get(Cart::class);
    }

    /**
     * @return void
     */
    #[DataFixture(ProductFixture::class, ['price' => 10], 'product')]
    public function testQuantityChangesAndRemoval(): void
    {
        $productId = (int)DataFixtureStorageManager::getStorage()->get('product')->getId();
        $this->saveCart($this->cart()->addProduct($productId, ['qty' => 2]));
        $this->assertSame([['Product Added', 2.0]], $this->events());

        $itemId = (int)$this->cart()->getQuote()->getAllVisibleItems()[0]->getId();
        $this->saveCart($this->cart()->updateItems([$itemId => ['qty' => 5]]));
        $this->assertSame([['Product Added', 3.0]], $this->events(), 'increase = difference');

        $this->saveCart($this->cart()->updateItems([$itemId => ['qty' => 1]]));
        $this->assertSame([['Product Removed', 4.0]], $this->events(), 'decrease = difference');

        $this->saveCart($this->cart()->removeItem($itemId));
        $this->assertSame([['Product Removed', 1.0]], $this->events(), 'removal = remaining qty');
    }

    /**
     * @return void
     */
    #[DataFixture(ProductFixture::class, ['price' => 10], 'product')]
    #[DataFixture(RuleFixture::class, ['coupon_code' => 'ZARAZ10', 'discount_amount' => 10])]
    public function testCouponAppliedAndRemoved(): void
    {
        $productId = (int)DataFixtureStorageManager::getStorage()->get('product')->getId();
        $this->saveCart($this->cart()->addProduct($productId, ['qty' => 1]));
        $this->events();

        $this->applyCoupon('ZARAZ10');
        $this->assertSame([['Coupon Applied', 'ZARAZ10']], $this->events());

        $this->applyCoupon('');
        $this->assertSame([['Coupon Removed', 'ZARAZ10']], $this->events());

        $this->applyCoupon('NOT-VALID');
        $this->assertSame([], $this->events(), 'an invalid coupon is reset by totals collection');
    }
}
