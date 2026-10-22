<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Price;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order\Item as OrderItem;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Price\PriceResolver;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriceResolverTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * @param array $settings
     * @return PriceResolver
     */
    private function createResolver(array $settings): PriceResolver
    {
        return new PriceResolver(
            $this->createConfig($settings),
            $this->createMock(CatalogHelper::class),
            $this->createMock(PriceCurrencyInterface::class)
        );
    }

    /**
     * @return array
     */
    public static function modeProvider(): array
    {
        return [
            'display excl tax' => [[], 20.0],
            'display incl tax' => [[Config::XML_PRODUCT_PRICE_TAX => '1'], 24.0],
            'base excl tax' => [[Config::XML_CURRENCY_SCOPE => Config::CURRENCY_BASE], 10.0],
            'base incl tax' => [
                [Config::XML_CURRENCY_SCOPE => Config::CURRENCY_BASE, Config::XML_PRODUCT_PRICE_TAX => '1'],
                12.0,
            ],
        ];
    }

    /**
     * Quote item "price" is in base currency, so unit prices come from row totals
     *
     * @param array $settings
     * @param float $expected
     * @return void
     */
    #[DataProvider('modeProvider')]
    public function testQuoteItemPrice(array $settings, float $expected): void
    {
        $item = (new ObjectManager($this))->getObject(QuoteItem::class);
        $item->setData([
            'store_id' => 1,
            'qty' => 3,
            'price' => 10.0,
            'row_total' => 60.0,
            'row_total_incl_tax' => 72.0,
            'base_row_total' => 30.0,
            'base_row_total_incl_tax' => 36.0,
        ]);

        $this->assertSame($expected, $this->createResolver($settings)->getQuoteItemPrice($item));
    }

    /**
     * @param array $settings
     * @param float $expected
     * @return void
     */
    #[DataProvider('modeProvider')]
    public function testOrderItemPrice(array $settings, float $expected): void
    {
        $item = (new ObjectManager($this))->getObject(OrderItem::class);
        $item->setData([
            'store_id' => 1,
            'price' => 20.0,
            'price_incl_tax' => 24.0,
            'base_price' => 10.0,
            'base_price_incl_tax' => 12.0,
        ]);

        $this->assertSame($expected, $this->createResolver($settings)->getOrderItemPrice($item));
    }

    /**
     * @return void
     */
    public function testQuoteCurrency(): void
    {
        $objectManager = new ObjectManager($this);
        $quote = $objectManager->getObject(Quote::class);
        $quote->setData(['base_currency_code' => 'EUR', 'quote_currency_code' => 'UAH']);
        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote'])
            ->getMock();
        $item->setData('store_id', 1);
        $item->method('getQuote')->willReturn($quote);

        $this->assertSame('UAH', $this->createResolver([])->getQuoteCurrency($item));
        $this->assertSame(
            'EUR',
            $this->createResolver([Config::XML_CURRENCY_SCOPE => Config::CURRENCY_BASE])->getQuoteCurrency($item)
        );
    }

    /**
     * Totals not collected (programmatic add without collectTotals): product final price, converted to display currency
     *
     * @return void
     */
    public function testQuoteItemPriceFallsBackToProductFinalPrice(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getFinalPrice')->with(2.0)->willReturn(10.0);
        $item = $this->getMockBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProduct', 'getQuote'])
            ->getMock();
        $item->method('getProduct')->willReturn($product);
        $item->method('getQuote')->willReturn(null);
        $item->setData(['store_id' => 1, 'qty' => 2]);

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('convert')->with(10.0)->willReturn(41.5);
        $resolver = new PriceResolver($this->createConfig([]), $this->createMock(CatalogHelper::class), $priceCurrency);

        $this->assertSame(41.5, $resolver->getQuoteItemPrice($item));
    }
}
