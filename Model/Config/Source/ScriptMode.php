<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Perspective\Zaraz\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class ScriptMode implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::SCRIPT_MODE_AUTO, 'label' => __('Auto-inject by Cloudflare')],
            ['value' => Config::SCRIPT_MODE_MANUAL, 'label' => __('Manual: the module prints the script tag')],
        ];
    }
}
