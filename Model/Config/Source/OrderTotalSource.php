<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Perspective\Zaraz\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class OrderTotalSource implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::TOTAL_GRAND_TOTAL, 'label' => __('Grand total')],
            ['value' => Config::TOTAL_SUBTOTAL, 'label' => __('Subtotal (after discount)')],
        ];
    }
}
