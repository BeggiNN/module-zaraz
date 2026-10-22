<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Perspective\Zaraz\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class VariantSource implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::VARIANT_OPTIONS, 'label' => __('Selected option values (e.g. "Red / M")')],
            ['value' => Config::VARIANT_CHILD_SKU, 'label' => __('Child product SKU')],
            ['value' => Config::VARIANT_CHILD_NAME, 'label' => __('Child product name')],
        ];
    }
}
