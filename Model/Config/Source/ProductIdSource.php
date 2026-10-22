<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Perspective\Zaraz\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class ProductIdSource implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::ID_SOURCE_SKU, 'label' => __('SKU')],
            ['value' => Config::ID_SOURCE_ENTITY, 'label' => __('Product ID (entity_id)')],
        ];
    }
}
