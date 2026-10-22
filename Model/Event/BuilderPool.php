<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event;

use Perspective\Zaraz\Api\EventBuilderInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Event builders by event code, configured in di.xml
 */
class BuilderPool
{
    /**
     * @param EventBuilderInterface[] $builders
     * @throws LocalizedException
     */
    public function __construct(
        private readonly array $builders = [],
    ) {
        foreach ($builders as $code => $builder) {
            if (!$builder instanceof EventBuilderInterface) {
                throw new LocalizedException(
                    __('Zaraz event builder "%1" must implement %2.', $code, EventBuilderInterface::class)
                );
            }
        }
    }

    /**
     * Builder for an event code
     *
     * @param string $code
     * @return EventBuilderInterface|null
     */
    public function get(string $code): ?EventBuilderInterface
    {
        return $this->builders[$code] ?? null;
    }

    /**
     * All builders keyed by event code
     *
     * @return EventBuilderInterface[]
     */
    public function getAll(): array
    {
        return $this->builders;
    }
}
