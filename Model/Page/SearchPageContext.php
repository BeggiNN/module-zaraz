<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Page;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\PageContextProviderInterface;
use Perspective\Zaraz\Model\Event\ContextFactory;

/**
 * Search query of catalogsearch/result (public: the page is cached per query)
 */
class SearchPageContext implements PageContextProviderInterface, ArgumentInterface
{
    /**
     * @param QueryFactory $queryFactory
     * @param StoreManagerInterface $storeManager
     * @param ContextFactory $contextFactory
     */
    public function __construct(
        private readonly QueryFactory $queryFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ContextFactory $contextFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getContext(): ?EventContextInterface
    {
        $query = trim((string)$this->queryFactory->get()->getQueryText());
        if ($query === '') {
            return null;
        }
        return $this->contextFactory->create([
            'storeId' => (int)$this->storeManager->getStore()->getId(),
            'data' => ['query' => $query],
        ]);
    }
}
