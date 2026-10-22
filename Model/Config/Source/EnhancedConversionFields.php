<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EnhancedConversionFields implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'email', 'label' => __('Email (SHA-256)')],
            ['value' => 'phone', 'label' => __('Phone (SHA-256)')],
            ['value' => 'first_name', 'label' => __('First name (SHA-256)')],
            ['value' => 'last_name', 'label' => __('Last name (SHA-256)')],
            ['value' => 'street', 'label' => __('Street (SHA-256)')],
            ['value' => 'city', 'label' => __('City')],
            ['value' => 'region', 'label' => __('Region')],
            ['value' => 'postal_code', 'label' => __('Postal code')],
            ['value' => 'country', 'label' => __('Country')],
        ];
    }
}
