<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event;

use Perspective\Zaraz\Api\EventGeneratorInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates an action event and puts it into the customer-data queue; tracking errors never break the action
 */
class ActionEvents
{
    /**
     * @param EventGeneratorInterface $generator
     * @param ContextFactory $contextFactory
     * @param DeliveryId $deliveryId
     * @param EventQueue $queue
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly EventGeneratorInterface $generator,
        private readonly ContextFactory $contextFactory,
        private readonly DeliveryId $deliveryId,
        private readonly EventQueue $queue,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Whether action events can be delivered in this request (frontend session)
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->queue->isAvailable();
    }

    /**
     * Queue an event
     *
     * @param string $code
     * @param array $context Arguments of Model\Event\Context (storeId, product, quoteItem, quote, qty, data)
     * @return void
     */
    public function queue(string $code, array $context): void
    {
        try {
            $event = $this->generator->generate(
                $code,
                $this->contextFactory->create($context),
                EventGeneratorInterface::CHANNEL_BROWSER,
                $this->deliveryId->generate($code),
            );
            if ($event !== null) {
                $this->queue->add($event);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Perspective_Zaraz: %s failed: %s', $code, $e->getMessage()),
                ['exception' => $e]
            );
        }
    }
}
