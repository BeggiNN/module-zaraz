<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Perspective\Zaraz\Model\Event\BuilderPool;
use Magento\Framework\Data\OptionSourceInterface;

class Events implements OptionSourceInterface
{
    /**
     * @param BuilderPool $builderPool
     */
    public function __construct(
        private readonly BuilderPool $builderPool,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->builderPool->getAll() as $code => $builder) {
            $options[] = [
                'value' => $code,
                'label' => sprintf('%s — %s(\'%s\')', $code, $builder->getMethod(), $builder->getDefaultName()),
            ];
        }
        return $options;
    }
}
