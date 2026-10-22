<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\BlockInterface;

class CustomAttributes extends AbstractFieldArray
{
    /**
     * @var BlockInterface|null
     */
    private ?BlockInterface $attributeRenderer = null;

    /**
     * @inheritDoc
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn('property', ['label' => __('Property name'), 'class' => 'required-entry input-text']);
        $this->addColumn('attribute', ['label' => __('Attribute'), 'renderer' => $this->getAttributeRenderer()]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add Attribute');
    }

    /**
     * @inheritDoc
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $hash = $this->getAttributeRenderer()->calcOptionHash((string)$row->getData('attribute'));
        $row->setData('option_extra_attrs', ['option_' . $hash => 'selected="selected"']);
    }

    /**
     * Attribute select renderer
     *
     * @return BlockInterface
     * @throws LocalizedException
     */
    private function getAttributeRenderer(): BlockInterface
    {
        if ($this->attributeRenderer === null) {
            $this->attributeRenderer = $this->getLayout()->createBlock(
                'PerspectiveZarazAttributeColumn',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->attributeRenderer;
    }
}
