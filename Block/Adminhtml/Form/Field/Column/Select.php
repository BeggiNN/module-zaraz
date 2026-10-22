<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Block\Adminhtml\Form\Field\Column;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select as HtmlSelect;

class Select extends HtmlSelect
{
    /**
     * @param Context $context
     * @param OptionSourceInterface $optionSource
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly OptionSourceInterface $optionSource,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Field name used by the dynamic rows template
     *
     * @param string $value
     * @return $this
     */
    public function setInputName(string $value): self
    {
        return $this->setName($value);
    }

    /**
     * Field id used by the dynamic rows template
     *
     * @param string $value
     * @return $this
     */
    public function setInputId(string $value): self
    {
        return $this->setId($value);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->optionSource->toOptionArray());
        }
        return parent::_toHtml();
    }
}
