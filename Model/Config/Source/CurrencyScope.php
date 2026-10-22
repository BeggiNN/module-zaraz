<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Perspective\Zaraz\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class CurrencyScope implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::CURRENCY_DISPLAY, 'label' => __('Display / order currency')],
            ['value' => Config::CURRENCY_BASE, 'label' => __('Base currency')],
        ];
    }
}
