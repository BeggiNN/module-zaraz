<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class ProductAttributes implements OptionSourceInterface
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
            ->addFieldToFilter('frontend_input', ['in' => ['text', 'select', 'multiselect', 'boolean']])
            ->setOrder('frontend_label', 'ASC');
        $options = [];
        foreach ($collection as $attribute) {
            $label = (string)$attribute->getFrontendLabel() ?: $attribute->getAttributeCode();
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => sprintf('%s (%s)', $label, $attribute->getAttributeCode()),
            ];
        }
        return $options;
    }
}
