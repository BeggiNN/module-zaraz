<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Page;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\PageContextProviderInterface;
use Perspective\Zaraz\Model\Event\ContextFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Product of catalog/product/view; the repository returns the instance already loaded by the controller
 */
class ProductPageContext implements PageContextProviderInterface, ArgumentInterface
{
    /**
     * @param RequestInterface $request
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param ContextFactory $contextFactory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ContextFactory $contextFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getContext(): ?EventContextInterface
    {
        $productId = (int)$this->request->getParam('id');
        if (!$productId) {
            return null;
        }
        $storeId = (int)$this->storeManager->getStore()->getId();
        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (NoSuchEntityException) {
            return null;
        }
        return $this->contextFactory->create(['storeId' => $storeId, 'product' => $product]);
    }
}
