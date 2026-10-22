<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api;

use Perspective\Zaraz\Api\Data\EventContextInterface;

/**
 * Provides the event context of the current page (product page, success page, ...).
 *
 * Used by the Perspective_Zaraz page events block; custom checkouts register their own provider in layout
 * (as a layout object argument the implementation must also implement
 * Magento\Framework\View\Element\Block\ArgumentInterface).
 * Context data key "delivery_id" makes the event at-most-once per browser.
 *
 * @api
 */
interface PageContextProviderInterface
{
    /**
     * Context or null when the page has nothing to report
     *
     * @return EventContextInterface|null
     */
    public function getContext(): ?EventContextInterface;
}
