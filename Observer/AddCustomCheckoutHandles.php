<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Config;

/**
 * layout_load_before: pages of custom checkouts get the module checkout/success layout handles.
 *
 * "Checkout paths" / "Success paths" entries match a full action name ("onestepcheckout_index_index") or the
 * beginning of the request path ("onestepcheckout", "checkout/success").
 */
class AddCustomCheckoutHandles implements ObserverInterface
{
    public const HANDLE_CHECKOUT = 'perspective_zaraz_checkout';
    public const HANDLE_SUCCESS = 'perspective_zaraz_success';

    private const NATIVE = [
        'checkout_index_index' => self::HANDLE_CHECKOUT,
        'checkout_onepage_success' => self::HANDLE_SUCCESS,
    ];

    /**
     * @param Config $config
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Config $config,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $fullActionName = (string)$observer->getEvent()->getData('full_action_name');
        $layout = $observer->getEvent()->getData('layout');
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$layout instanceof LayoutInterface || isset(self::NATIVE[$fullActionName])
            || !$this->config->isEnabled($storeId)
        ) {
            return;
        }
        $path = trim((string)$this->request->getPathInfo(), '/');
        if ($this->matches($this->config->getSuccessPaths($storeId), $fullActionName, $path)) {
            $layout->getUpdate()->addHandle(self::HANDLE_SUCCESS);
        } elseif ($this->matches($this->config->getCheckoutPaths($storeId), $fullActionName, $path)) {
            $layout->getUpdate()->addHandle(self::HANDLE_CHECKOUT);
        }
    }

    /**
     * Whether the page matches one of the entries
     *
     * @param string[] $entries
     * @param string $fullActionName
     * @param string $path
     * @return bool
     */
    private function matches(array $entries, string $fullActionName, string $path): bool
    {
        foreach ($entries as $entry) {
            $entry = trim($entry, '/');
            if ($entry !== '' && ($entry === $fullActionName || str_starts_with($path, $entry))) {
                return true;
            }
        }
        return false;
    }
}
