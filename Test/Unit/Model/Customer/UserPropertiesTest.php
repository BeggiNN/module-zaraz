<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Customer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Session;
use Perspective\Zaraz\Api\Data\EventInterfaceFactory;
use Perspective\Zaraz\Model\Customer\UserProperties;
use Perspective\Zaraz\Model\Event\Event;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class UserPropertiesTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * @param bool $loggedIn
     * @param array $settings
     * @return UserProperties
     */
    private function create(bool $loggedIn, array $settings): UserProperties
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn(' Buyer@Example.com');
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isLoggedIn', 'getCustomerData', 'getCustomerGroupId'])
            ->getMock();
        $session->method('isLoggedIn')->willReturn($loggedIn);
        $session->method('getCustomerData')->willReturn($customer);
        $session->method('getCustomerGroupId')->willReturn($loggedIn ? 1 : 0);
        $group = $this->createMock(GroupInterface::class);
        $group->method('getCode')->willReturnCallback(static fn () => $loggedIn ? 'General' : 'NOT LOGGED IN');
        $groups = $this->createMock(GroupRepositoryInterface::class);
        $groups->method('getById')->willReturn($group);
        $factory = $this->createMock(EventInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (array $data): Event => new Event(...$data));

        return new UserProperties($this->createConfig($settings), $session, $groups, $factory);
    }

    /**
     * @param array $events
     * @return array
     */
    private function toArray(array $events): array
    {
        $result = [];
        foreach ($events as $event) {
            $result[$event->getName()] = $event->getPayload();
        }
        return $result;
    }

    /**
     * @return void
     */
    public function testCustomerPropertiesAndKeys(): void
    {
        $settings = [
            'perspective_zaraz/user/customer_id' => '1',
            'perspective_zaraz/user/customer_group' => '1',
            'perspective_zaraz/user/customer_group_key' => 'group',
            'perspective_zaraz/user/email_hash' => '1',
            'perspective_zaraz/user/email_hash_key' => 'em',
        ];
        $this->assertSame(
            [
                'customer_id' => ['value' => '42', 'scope' => 'session'],
                'group' => ['value' => 'General', 'scope' => 'session'],
                'em' => ['value' => hash('sha256', 'buyer@example.com'), 'scope' => 'session'],
            ],
            $this->toArray($this->create(true, $settings)->getCustomerEvents(1))
        );
        $this->assertSame(
            [
                'customer_id' => ['value' => null, 'scope' => 'session'],
                'group' => ['value' => 'NOT LOGGED IN', 'scope' => 'session'],
                'em' => ['value' => null, 'scope' => 'session'],
            ],
            $this->toArray($this->create(false, $settings)->getCustomerEvents(1)),
            'guests unset customer keys (null)'
        );
        $this->assertSame([], $this->create(true, [])->getCustomerEvents(1), 'all off');
    }

    /**
     * @return void
     */
    public function testPageProperties(): void
    {
        $properties = $this->create(false, [
            'perspective_zaraz/user/page_type' => '1',
            'perspective_zaraz/user/page_name' => '1',
        ]);
        $this->assertSame(
            [
                'page_type' => ['value' => 'category', 'scope' => 'page'],
                'page_name' => ['value' => 'Jackets', 'scope' => 'page'],
            ],
            $this->toArray($properties->getPageEvents('catalog_category_view', 'Jackets', 1))
        );
        $this->assertSame('other', $properties->getPageType('some_custom_action'));
    }
}
