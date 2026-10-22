<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Price;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Price\OrderTotals;
use Perspective\Zaraz\Model\Price\PriceResolver;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderTotalsTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * Order: subtotal 100, discount -10, shipping 20, tax 18 (of which shipping tax 4), grand total 128.
     * Base currency amounts are exactly half.
     *
     * @return OrderInterface
     */
    private function createOrder(): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getGrandTotal')->willReturn(128.0);
        $order->method('getSubtotal')->willReturn(100.0);
        $order->method('getDiscountAmount')->willReturn(-10.0);
        $order->method('getShippingAmount')->willReturn(20.0);
        $order->method('getTaxAmount')->willReturn(18.0);
        $order->method('getShippingTaxAmount')->willReturn(4.0);
        $order->method('getBaseGrandTotal')->willReturn(64.0);
        $order->method('getBaseSubtotal')->willReturn(50.0);
        $order->method('getBaseDiscountAmount')->willReturn(-5.0);
        $order->method('getBaseShippingAmount')->willReturn(10.0);
        $order->method('getBaseTaxAmount')->willReturn(9.0);
        $order->method('getBaseShippingTaxAmount')->willReturn(2.0);
        return $order;
    }

    /**
     * @return array
     */
    public static function settingsProvider(): array
    {
        $grand = [Config::XML_ORDER_TOTAL_SOURCE => Config::TOTAL_GRAND_TOTAL];
        $subtotal = [Config::XML_ORDER_TOTAL_SOURCE => Config::TOTAL_SUBTOTAL];
        $common = ['revenue' => 90.0, 'tax' => 18.0, 'discount' => 10.0];

        return [
            'grand total as is' => [$grand, ['total' => 128.0, 'shipping' => 20.0] + $common],
            'grand total without tax' => [
                $grand + [Config::XML_EXCLUDE_TAX => '1'],
                ['total' => 110.0, 'shipping' => 20.0] + $common,
            ],
            'grand total without shipping' => [
                $grand + [Config::XML_EXCLUDE_SHIPPING => '1'],
                ['total' => 108.0, 'shipping' => 20.0] + $common,
            ],
            'grand total without shipping tax' => [
                $grand + [Config::XML_EXCLUDE_SHIPPING_TAX => '1'],
                ['total' => 124.0, 'shipping' => 20.0] + $common,
            ],
            'shipping tax flag is ignored when all tax is excluded' => [
                $grand + [Config::XML_EXCLUDE_TAX => '1', Config::XML_EXCLUDE_SHIPPING_TAX => '1'],
                ['total' => 110.0, 'shipping' => 20.0] + $common,
            ],
            'grand total without tax and shipping' => [
                $grand + [Config::XML_EXCLUDE_TAX => '1', Config::XML_EXCLUDE_SHIPPING => '1'],
                ['total' => 90.0, 'shipping' => 20.0] + $common,
            ],
            'shipping including tax' => [
                $grand + [Config::XML_SHIPPING_INCL_TAX => '1'],
                ['total' => 128.0, 'shipping' => 24.0] + $common,
            ],
            'subtotal with item tax' => [$subtotal, ['total' => 104.0, 'shipping' => 20.0] + $common],
            'subtotal without tax' => [
                $subtotal + [Config::XML_EXCLUDE_TAX => '1'],
                ['total' => 90.0, 'shipping' => 20.0] + $common,
            ],
            'subtotal ignores shipping exclusion flags' => [
                $subtotal + [Config::XML_EXCLUDE_SHIPPING => '1', Config::XML_EXCLUDE_SHIPPING_TAX => '1'],
                ['total' => 104.0, 'shipping' => 20.0] + $common,
            ],
            'base currency' => [
                $grand + [Config::XML_CURRENCY_SCOPE => Config::CURRENCY_BASE],
                ['total' => 64.0, 'revenue' => 45.0, 'shipping' => 10.0, 'tax' => 9.0, 'discount' => 5.0],
            ],
        ];
    }

    /**
     * @param array $settings
     * @param array $expected
     * @return void
     */
    #[DataProvider('settingsProvider')]
    public function testCalculate(array $settings, array $expected): void
    {
        $config = $this->createConfig($settings);
        $priceResolver = new PriceResolver(
            $config,
            $this->createMock(CatalogHelper::class),
            $this->createMock(PriceCurrencyInterface::class)
        );
        $totals = (new OrderTotals($config, $priceResolver))->calculate($this->createOrder());

        ksort($expected);
        ksort($totals);
        $this->assertSame($expected, $totals);
    }
}
