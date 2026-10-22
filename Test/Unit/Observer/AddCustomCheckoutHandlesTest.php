<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Model\Layout\Merge;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Observer\AddCustomCheckoutHandles;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddCustomCheckoutHandlesTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * @return array
     */
    public static function pageProvider(): array
    {
        return [
            'custom checkout by action' => ['onestepcheckout_index_index', 'osc', 'perspective_zaraz_checkout'],
            'custom checkout by path' => ['other_index_index', 'onestepcheckout/step/2', 'perspective_zaraz_checkout'],
            'custom success page' => ['osc_success_index', 'osc/order/success', 'perspective_zaraz_success'],
            'native checkout is handled by its own layout' => ['checkout_index_index', 'onestepcheckout', null],
            'other page' => ['cms_index_index', 'home', null],
        ];
    }

    /**
     * @param string $fullActionName
     * @param string $path
     * @param string|null $expectedHandle
     * @return void
     */
    #[DataProvider('pageProvider')]
    public function testHandles(string $fullActionName, string $path, ?string $expectedHandle): void
    {
        $update = $this->createMock(Merge::class);
        $update->expects($expectedHandle ? $this->once() : $this->never())
            ->method('addHandle')
            ->with($expectedHandle);
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getUpdate')->willReturn($update);
        $request = $this->createMock(Http::class);
        $request->method('getPathInfo')->willReturn('/' . $path . '/');
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $observer = new AddCustomCheckoutHandles(
            $this->createConfig([
                Config::XML_CHECKOUT_PATHS => "onestepcheckout_index_index\nonestepcheckout",
                Config::XML_SUCCESS_PATHS => 'osc/order/success',
            ]),
            $request,
            $storeManager
        );
        $observer->execute(new Observer(['event' => new Event([
            'full_action_name' => $fullActionName,
            'layout' => $layout,
        ])]));
    }
}
