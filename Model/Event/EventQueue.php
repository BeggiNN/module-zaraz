<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\Config\ConfigInterface as SessionConfig;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\CookieReaderInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Perspective\Zaraz\Api\Data\EventInterface;

/**
 * Queue of action events (cart, wishlist, login, ...)
 *
 * Delivered to the browser via the "perspective-zaraz" customer-data section. Used in the frontend area and in REST
 * calls of the storefront (e.g. Luma checkout coupons) that carry the session cookie; token-based REST clients
 * (headless) never start a session here.
 *
 * While the queue is not empty a public cookie "perspective_zaraz_pending" tells the browser adapter to reload the
 * section on the next page: actions that end with a native form submit or a redirect do not invalidate customer-data
 * sections in the browser (e.g. the Luma cart update posts with form.submit()).
 */
class EventQueue
{
    public const PENDING_COOKIE = 'perspective_zaraz_pending';

    private const KEY = 'events';

    /**
     * @param SessionManagerInterface $session Perspective\Zaraz\Model\Session\Proxy
     * @param State $appState
     * @param CookieReaderInterface $cookieReader
     * @param SessionConfig $sessionConfig
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param int $limit Oldest events are dropped above this size (sessions of clients that never read the section)
     */
    public function __construct(
        private readonly SessionManagerInterface $session,
        private readonly State $appState,
        private readonly CookieReaderInterface $cookieReader,
        private readonly SessionConfig $sessionConfig,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly int $limit = 50,
    ) {
    }

    /**
     * Add an event for the next section load
     *
     * @param EventInterface $event
     * @return void
     */
    public function add(EventInterface $event): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        $events = $this->read();
        $events[] = $event->toArray();
        $this->session->setData(self::KEY, array_slice($events, -$this->limit));
        $this->setPendingCookie(true);
    }

    /**
     * Return queued events and clear the queue (read-once, so a reload does not repeat them)
     *
     * @return array[]
     */
    public function pull(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        $events = $this->read();
        if ($events) {
            $this->session->setData(self::KEY, []);
        }
        if ($this->cookieReader->getCookie(self::PENDING_COOKIE) !== null) {
            $this->setPendingCookie(false);
        }
        return $events;
    }

    /**
     * Whether the request belongs to a browser session
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $area = $this->appState->getAreaCode();
        } catch (LocalizedException) {
            return false;
        }
        if ($area === Area::AREA_FRONTEND) {
            return true;
        }
        return $area === Area::AREA_WEBAPI_REST
            && $this->cookieReader->getCookie((string)$this->sessionConfig->getName()) !== null;
    }

    /**
     * Current queue
     *
     * @return array[]
     */
    private function read(): array
    {
        $events = $this->session->getData(self::KEY);
        return is_array($events) ? $events : [];
    }

    /**
     * Set or delete the public "events pending" cookie
     *
     * @param bool $pending
     * @return void
     */
    private function setPendingCookie(bool $pending): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath('/')
            ->setHttpOnly(false)
            ->setSameSite('Lax');
        try {
            if ($pending) {
                $this->cookieManager->setPublicCookie(self::PENDING_COOKIE, '1', $metadata->setDuration(3600));
            } else {
                $this->cookieManager->deleteCookie(self::PENDING_COOKIE, $metadata);
            }
        } catch (\Exception) {
            // Headers already sent or cookie limits: the section is still reloaded by sections.xml invalidation
            return;
        }
    }
}
