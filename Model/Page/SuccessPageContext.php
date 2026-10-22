<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Page;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\PageContextProviderInterface;
use Perspective\Zaraz\Model\Event\ContextFactory;
use Perspective\Zaraz\Model\Event\DeliveryId;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Last placed order on the success page.
 *
 * Reload protection is two-layered: the order is rendered only once per checkout session (server),
 * and the event carries a stable delivery id that the JS core sends once per browser.
 */
class SuccessPageContext implements PageContextProviderInterface, ArgumentInterface
{
    private const SESSION_KEY = 'perspective_zaraz_rendered_orders';

    /**
     * @param CheckoutSession $checkoutSession
     * @param ContextFactory $contextFactory
     * @param DeliveryId $deliveryId
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ContextFactory $contextFactory,
        private readonly DeliveryId $deliveryId,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getContext(): ?EventContextInterface
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $incrementId = (string)$order->getIncrementId();
        if (!$order->getId() || $incrementId === '') {
            return null;
        }
        $rendered = (array)$this->checkoutSession->getData(self::SESSION_KEY);
        if (in_array($incrementId, $rendered, true)) {
            return null;
        }
        $rendered[] = $incrementId;
        $this->checkoutSession->setData(self::SESSION_KEY, array_slice($rendered, -20));

        return $this->contextFactory->create([
            'storeId' => (int)$order->getStoreId(),
            'order' => $order,
            'data' => ['delivery_id' => $this->deliveryId->forOrder($incrementId)],
        ]);
    }
}
