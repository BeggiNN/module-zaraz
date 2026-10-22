<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Perspective\Zaraz\Model\Config;

class CategoryFormat implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::CATEGORY_FORMAT_PATH, 'label' => __('Path: category = "Men/Tops/Jackets"')],
            ['value' => Config::CATEGORY_FORMAT_LEVELS, 'label' => __('Levels: category, category2 … category5')],
        ];
    }
}
