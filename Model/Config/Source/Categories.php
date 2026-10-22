<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class Categories implements OptionSourceInterface
{
    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect('name')
            ->addFieldToFilter('level', ['gt' => 0])
            ->setOrder('path', 'ASC');
        $options = [];
        foreach ($collection as $category) {
            $options[] = [
                'value' => (string)$category->getId(),
                'label' => str_repeat('— ', max(0, (int)$category->getLevel() - 1))
                    . $category->getName() . ' (ID ' . $category->getId() . ')',
            ];
        }
        return $options;
    }
}
