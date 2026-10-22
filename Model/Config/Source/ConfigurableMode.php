<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Perspective\Zaraz\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class ConfigurableMode implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::CONFIGURABLE_PARENT, 'label' => __('Parent (configurable) product')],
            ['value' => Config::CONFIGURABLE_CHILD, 'label' => __('Selected child (simple) product')],
        ];
    }
}
