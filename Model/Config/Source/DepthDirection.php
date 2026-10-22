<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Perspective\Zaraz\Model\Config;

class DepthDirection implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::DEPTH_FROM_ROOT, 'label' => __('From the root (top levels)')],
            ['value' => Config::DEPTH_FROM_LEAF, 'label' => __('From the product category (deepest levels)')],
        ];
    }
}
