<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Mapper\Modifier;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Customer\OrderHistory;
use Perspective\Zaraz\Model\Mapper\Modifier\Order\GoogleAds;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class GoogleAdsTest extends TestCase
{
    use ConfigStubTrait;

    private const PAYLOAD = [
        'order_id' => '1',
        'products' => [['product_id' => 'MH01', 'price' => 52.0, 'quantity' => 2.0]],
    ];

    /**
     * @param float $grandTotal
     * @return OrderInterface
     */
    private function order(float $grandTotal = 104.0): OrderInterface
    {
        $address = $this->createMock(OrderAddressInterface::class);
        $address->method('getTelephone')->willReturn('+38 (050) 123-45-67');
        $address->method('getFirstname')->willReturn(' Ivan ');
        $address->method('getLastname')->willReturn('Test');
        $address->method('getStreet')->willReturn(['1 Main St', 'Apt 2']);
        $address->method('getCity')->willReturn('Kyiv');
        $address->method('getRegion')->willReturn('Kyiv');
        $address->method('getPostcode')->willReturn('01001');
        $address->method('getCountryId')->willReturn('UA');
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getBaseGrandTotal')->willReturn($grandTotal);
        $order->method('getCustomerEmail')->willReturn(' Buyer@Example.com ');
        $order->method('getBillingAddress')->willReturn($address);
        return $order;
    }

    /**
     * @param array $settings
     * @return GoogleAds
     */
    private function modifier(array $settings): GoogleAds
    {
        $history = $this->createMock(OrderHistory::class);
        $history->method('isNewCustomer')->willReturn(true);
        $history->method('getLifetimeValue')->willReturn(250.5);
        return new GoogleAds($this->createConfig($settings), $history);
    }

    /**
     * @return array
     */
    private function allOptions(): array
    {
        return [
            'perspective_zaraz/ads/enabled' => '1',
            'perspective_zaraz/ads/new_customer' => '1',
            'perspective_zaraz/ads/lifetime_value' => '1',
            'perspective_zaraz/ads/cart_items' => '1',
            'perspective_zaraz/ads/enhanced_conversions' => '1',
            'perspective_zaraz/ads/skip_zero_total' => '1',
            Config::XML_ADS_EC_FIELDS => 'email,phone,first_name,street,city,country',
        ];
    }

    /**
     * @return void
     */
    public function testAllOptions(): void
    {
        $data = $this->modifier($this->allOptions())->modify(self::PAYLOAD, $this->order());

        $this->assertTrue($data['new_customer']);
        $this->assertSame(250.5, $data['customer_lifetime_value']);
        $this->assertSame([['id' => 'MH01', 'quantity' => 2.0, 'price' => 52.0]], $data['ads_items']);
        $this->assertSame(hash('sha256', 'buyer@example.com'), $data['ec_email']);
        $this->assertSame(hash('sha256', '+380501234567'), $data['ec_phone']);
        $this->assertSame(hash('sha256', 'ivan'), $data['ec_first_name']);
        $this->assertSame(hash('sha256', '1 main st apt 2'), $data['ec_street']);
        $this->assertSame('Kyiv', $data['ec_city'], 'city is not hashed');
        $this->assertSame('UA', $data['ec_country']);
        $this->assertArrayNotHasKey('ec_last_name', $data, 'only selected fields');
    }

    /**
     * @return void
     */
    public function testDisabledAndZeroTotal(): void
    {
        $this->assertSame(self::PAYLOAD, $this->modifier([])->modify(self::PAYLOAD, $this->order()));
        $this->assertSame(
            self::PAYLOAD,
            $this->modifier($this->allOptions())->modify(self::PAYLOAD, $this->order(0.0))
        );
    }
}
