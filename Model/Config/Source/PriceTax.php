<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PriceTax implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '0', 'label' => __('Excluding tax')],
            ['value' => '1', 'label' => __('Including tax')],
        ];
    }
}
