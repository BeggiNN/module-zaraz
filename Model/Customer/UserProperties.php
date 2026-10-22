<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Customer;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\Data\EventInterfaceFactory;
use Perspective\Zaraz\Model\Config;

class UserProperties
{
    public const SCOPE_PAGE = 'page';
    public const SCOPE_SESSION = 'session';

    private const PAGE_TYPES = [
        'cms_index_index' => 'home',
        'cms_page_view' => 'cms',
        'catalog_category_view' => 'category',
        'catalog_product_view' => 'product',
        'catalogsearch_result_index' => 'search',
        'catalogsearch_advanced_result' => 'search',
        'checkout_cart_index' => 'cart',
        'checkout_index_index' => 'checkout',
        'checkout_onepage_success' => 'purchase',
        'wishlist_index_index' => 'wishlist',
        'catalog_product_compare_index' => 'compare',
    ];

    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param GroupRepositoryInterface $groupRepository
     * @param EventInterfaceFactory $eventFactory
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly EventInterfaceFactory $eventFactory,
    ) {
    }

    /**
     * Customer properties of the current visitor
     *
     * @param int $storeId
     * @return EventInterface[]
     */
    public function getCustomerEvents(int $storeId): array
    {
        $loggedIn = $this->customerSession->isLoggedIn();
        $customer = $loggedIn ? $this->customerSession->getCustomerData() : null;
        $values = [
            'customer_id' => $customer ? (string)$customer->getId() : null,
            'customer_group' => $this->getGroupCode((int)$this->customerSession->getCustomerGroupId()),
            'email_hash' => $customer ? hash('sha256', strtolower(trim((string)$customer->getEmail()))) : null,
        ];
        $events = [];
        foreach ($values as $property => $value) {
            $key = $this->config->getUserPropertyKey($property, $storeId);
            if ($key !== null) {
                $events[] = $this->createSet($key, $value, self::SCOPE_SESSION);
            }
        }
        return $events;
    }

    /**
     * Page properties (page_type from the route, page_name from the page title)
     *
     * @param string $fullActionName
     * @param string $pageName
     * @param int $storeId
     * @return EventInterface[]
     */
    public function getPageEvents(string $fullActionName, string $pageName, int $storeId): array
    {
        $events = [];
        $typeKey = $this->config->getUserPropertyKey('page_type', $storeId);
        if ($typeKey !== null) {
            $events[] = $this->createSet($typeKey, self::PAGE_TYPES[$fullActionName] ?? 'other', self::SCOPE_PAGE);
        }
        $nameKey = $this->config->getUserPropertyKey('page_name', $storeId);
        if ($nameKey !== null && $pageName !== '') {
            $events[] = $this->createSet($nameKey, $pageName, self::SCOPE_PAGE);
        }
        return $events;
    }

    /**
     * Page type of a route
     *
     * @param string $fullActionName
     * @return string
     */
    public function getPageType(string $fullActionName): string
    {
        return self::PAGE_TYPES[$fullActionName] ?? 'other';
    }

    /**
     * Customer group code
     *
     * @param int $groupId
     * @return string|null
     */
    private function getGroupCode(int $groupId): ?string
    {
        try {
            return (string)$this->groupRepository->getById($groupId)->getCode();
        } catch (LocalizedException) {
            return null;
        }
    }

    /**
     * Event for zaraz.set(key, value, {scope})
     *
     * @param string $key
     * @param string|null $value
     * @param string $scope
     * @return EventInterface
     */
    private function createSet(string $key, ?string $value, string $scope): EventInterface
    {
        return $this->eventFactory->create([
            'method' => EventInterface::METHOD_SET,
            'name' => $key,
            'payload' => ['value' => $value, 'scope' => $scope],
        ]);
    }
}
