<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\Data\EventInterfaceFactory;
use Perspective\Zaraz\Api\EventGeneratorInterface;
use Perspective\Zaraz\Model\Config;

/**
 * Applies store configuration to builders
 */
class Generator implements EventGeneratorInterface
{
    /**
     * @param BuilderPool $builderPool
     * @param Config $config
     * @param EventInterfaceFactory $eventFactory
     * @param array $defaultChannels event code => channels (string[]) used when the event has no row in "Events"
     *        (server-only events of Perspective_ZarazServerSide); other codes default to the browser
     */
    public function __construct(
        private readonly BuilderPool $builderPool,
        private readonly Config $config,
        private readonly EventInterfaceFactory $eventFactory,
        private readonly array $defaultChannels = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function generate(
        string $code,
        EventContextInterface $context,
        string $channel = self::CHANNEL_BROWSER,
        ?string $id = null,
    ): ?EventInterface {
        $storeId = $context->getStoreId();
        $builder = $this->builderPool->get($code);
        if ($builder === null || !$this->isAllowed($code, $channel, $storeId)) {
            return null;
        }
        $payload = $builder->build($context);
        if ($payload === null) {
            return null;
        }
        $name = $this->config->getEventSettings($code, $storeId)['name'] ?? '';

        return $this->eventFactory->create([
            'method' => $builder->getMethod(),
            'name' => $name !== '' ? $name : $builder->getDefaultName(),
            'payload' => $payload,
            'id' => $id,
            'code' => $code,
        ]);
    }

    /**
     * Whether the event is enabled for the channel; an event without an admin row uses its default channels
     *
     * @param string $code
     * @param string $channel
     * @param int $storeId
     * @return bool
     */
    public function isAllowed(string $code, string $channel, int $storeId): bool
    {
        if (!$this->config->isEnabled($storeId)) {
            return false;
        }
        $settings = $this->config->getEventSettings($code, $storeId);
        if ($settings === null) {
            return in_array($channel, $this->defaultChannels[$code] ?? [self::CHANNEL_BROWSER], true);
        }
        return $settings['enabled'] && ($settings[$channel] ?? false);
    }
}
