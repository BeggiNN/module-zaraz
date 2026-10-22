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

class Events extends AbstractFieldArray
{
    /**
     * @var array<string, BlockInterface>
     */
    private array $renderers = [];

    /**
     * @inheritDoc
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn('event', ['label' => __('Event'), 'renderer' => $this->getRenderer('event')]);
        $this->addColumn('enabled', ['label' => __('Enabled'), 'renderer' => $this->getRenderer('enabled')]);
        $this->addColumn('name', ['label' => __('Zaraz event name (empty = default)'), 'class' => 'input-text']);
        $this->addColumn('browser', ['label' => __('From browser'), 'renderer' => $this->getRenderer('browser')]);
        $this->addColumn('server', ['label' => __('From server'), 'renderer' => $this->getRenderer('server')]);
        $this->_addAfter = false;
        $this->_addButtonLabel = (string)__('Add Event');
    }

    /**
     * @inheritDoc
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        foreach (['event', 'enabled', 'browser', 'server'] as $column) {
            $renderer = $this->getRenderer($column);
            $options['option_' . $renderer->calcOptionHash((string)$row->getData($column))] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Select renderer of a column
     *
     * @param string $column
     * @return BlockInterface
     * @throws LocalizedException
     */
    private function getRenderer(string $column): BlockInterface
    {
        if (!isset($this->renderers[$column])) {
            $this->renderers[$column] = $this->getLayout()->createBlock(
                $column === 'event' ? 'PerspectiveZarazEventColumn' : 'PerspectiveZarazYesNoColumn',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->renderers[$column];
    }
}
