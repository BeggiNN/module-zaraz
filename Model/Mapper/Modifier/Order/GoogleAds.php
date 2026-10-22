<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper\Modifier\Order;

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Perspective\Zaraz\Api\OrderDataModifierInterface;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Customer\OrderHistory;

/**
 * Google Ads data on Order Completed (the Ads conversion itself is configured as an action in Zaraz):
 * new_customer, customer_lifetime_value (base currency), ads_items, Enhanced Conversions fields.
 *
 * Enhanced Conversions: email, phone, first/last name and street are SHA-256 of normalized values, city, region,
 * postal code and country are sent as is (Google's requirements). Support of these fields by the Zaraz Google Ads
 * tool is not confirmed by Cloudflare documentation.
 */
class GoogleAds implements OrderDataModifierInterface
{
    /**
     * @param Config $config
     * @param OrderHistory $orderHistory
     */
    public function __construct(
        private readonly Config $config,
        private readonly OrderHistory $orderHistory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function modify(array $data, OrderInterface $order): array
    {
        $storeId = (int)$order->getStoreId();
        if (!$this->config->isAdsOption('enabled', $storeId)
            || ($this->config->isAdsOption('skip_zero_total', $storeId) && (float)$order->getBaseGrandTotal() <= 0)
        ) {
            return $data;
        }
        if ($this->config->isAdsOption('new_customer', $storeId)) {
            $data['new_customer'] = $this->orderHistory->isNewCustomer($order);
        }
        if ($this->config->isAdsOption('lifetime_value', $storeId)) {
            $data['customer_lifetime_value'] = $this->orderHistory->getLifetimeValue($order);
        }
        if ($this->config->isAdsOption('cart_items', $storeId)) {
            $data['ads_items'] = array_map(
                static fn (array $product): array => [
                    'id' => (string)($product['product_id'] ?? ''),
                    'quantity' => $product['quantity'] ?? 1,
                    'price' => $product['price'] ?? 0,
                ],
                $data['products'] ?? []
            );
        }
        if ($this->config->isAdsOption('enhanced_conversions', $storeId)) {
            $data += $this->getEnhancedConversions($order, $this->config->getEnhancedConversionFields($storeId));
        }
        return $data;
    }

    /**
     * Enhanced Conversions fields
     *
     * @param OrderInterface $order
     * @param string[] $fields
     * @return array<string, string>
     */
    private function getEnhancedConversions(OrderInterface $order, array $fields): array
    {
        $address = $order->getBillingAddress();
        $street = $address instanceof OrderAddressInterface ? implode(' ', (array)$address->getStreet()) : '';
        $phone = (string)$address?->getTelephone();
        $values = [
            'email' => $this->hash((string)$order->getCustomerEmail()),
            'phone' => $phone !== '' ? $this->hash(
                (str_starts_with(trim($phone), '+') ? '+' : '') . preg_replace('/\D+/', '', $phone)
            ) : '',
            'first_name' => $this->hash((string)($address?->getFirstname() ?? $order->getCustomerFirstname())),
            'last_name' => $this->hash((string)($address?->getLastname() ?? $order->getCustomerLastname())),
            'street' => $this->hash($street),
            'city' => trim((string)$address?->getCity()),
            'region' => trim((string)$address?->getRegion()),
            'postal_code' => trim((string)$address?->getPostcode()),
            'country' => trim((string)$address?->getCountryId()),
        ];
        $result = [];
        foreach ($fields as $field) {
            if (($values[$field] ?? '') !== '') {
                $result['ec_' . $field] = $values[$field];
            }
        }
        return $result;
    }

    /**
     * SHA-256 of the trimmed lowercase value, empty for empty values
     *
     * @param string $value
     * @return string
     */
    private function hash(string $value): string
    {
        $value = strtolower(trim($value));
        return $value !== '' ? hash('sha256', $value) : '';
    }
}
