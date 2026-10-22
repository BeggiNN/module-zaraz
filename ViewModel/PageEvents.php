<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\ViewModel;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Api\EventGeneratorInterface;
use Perspective\Zaraz\Api\PageContextProviderInterface;
use Perspective\Zaraz\Model\Customer\UserProperties;
use Psr\Log\LoggerInterface;

/**
 * Page events (sent on load) and templates (sent by the JS adapter on a browser action) as JSON for the JS core.
 *
 * Only data that is public for the page variant goes here: the HTML may be stored in FPC/Varnish.
 */
class PageEvents implements ArgumentInterface
{
    /**
     * @param EventGeneratorInterface $generator
     * @param JsonHexTag $json
     * @param LoggerInterface $logger
     * @param UserProperties $userProperties
     * @param HttpRequest $request
     * @param PageConfig $pageConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly EventGeneratorInterface $generator,
        private readonly JsonHexTag $json,
        private readonly LoggerInterface $logger,
        private readonly UserProperties $userProperties,
        private readonly HttpRequest $request,
        private readonly PageConfig $pageConfig,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * JSON array of events to send on page load, or null when there is nothing to send
     *
     * @param PageContextProviderInterface|null $contextProvider
     * @param array $eventCodes
     * @return string|null
     */
    public function getEventsJson(?PageContextProviderInterface $contextProvider, array $eventCodes): ?string
    {
        $events = $this->generate($contextProvider, $eventCodes);
        return $events ? $this->json->serialize(array_values($events)) : null;
    }

    /**
     * JSON object {code: event} of templates the JS adapter completes and sends on a browser action
     *
     * @param PageContextProviderInterface|null $contextProvider
     * @param array $eventCodes
     * @return string|null
     */
    public function getTemplatesJson(?PageContextProviderInterface $contextProvider, array $eventCodes): ?string
    {
        $events = $this->generate($contextProvider, $eventCodes);
        return $events ? $this->json->serialize($events) : null;
    }

    /**
     * JSON array of zaraz.set() events for page properties (page type, page name)
     *
     * @return string|null
     */
    public function getPagePropertiesJson(): ?string
    {
        try {
            $events = $this->userProperties->getPageEvents(
                (string)$this->request->getFullActionName(),
                trim((string)$this->pageConfig->getTitle()->get()),
                (int)$this->storeManager->getStore()->getId()
            );
        } catch (\Throwable $e) {
            $this->logger->error('Perspective_Zaraz: page properties failed: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
        return $events
            ? $this->json->serialize(array_map(static fn ($event): array => $event->toArray(), $events))
            : null;
    }

    /**
     * Events keyed by code
     *
     * @param PageContextProviderInterface|null $contextProvider
     * @param array $eventCodes
     * @return array<string, array>
     */
    private function generate(?PageContextProviderInterface $contextProvider, array $eventCodes): array
    {
        if ($contextProvider === null || !$eventCodes) {
            return [];
        }
        try {
            $context = $contextProvider->getContext();
            if ($context === null) {
                return [];
            }
            $deliveryId = $context->getData('delivery_id');
            $events = [];
            foreach ($eventCodes as $code) {
                $event = $this->generator->generate(
                    (string)$code,
                    $context,
                    EventGeneratorInterface::CHANNEL_BROWSER,
                    $deliveryId !== null ? (string)$deliveryId : null,
                );
                if ($event !== null) {
                    $events[(string)$code] = $event->toArray();
                }
            }
            return $events;
        } catch (\Throwable $e) {
            // Tracking must never break the page
            $this->logger->error('Perspective_Zaraz: page events failed: ' . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }
}
