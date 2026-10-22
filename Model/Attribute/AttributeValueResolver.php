<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Attribute;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Framework\Exception\LocalizedException;

class AttributeValueResolver
{
    /**
     * @var array<string, array<int, array<string, string>>>
     */
    private array $labels = [];

    /**
     * @param EavConfig $eavConfig
     */
    public function __construct(
        private readonly EavConfig $eavConfig,
    ) {
    }

    /**
     * Load option labels used by the products in one query per attribute
     *
     * @param ProductInterface[] $products
     * @param string[] $attributeCodes
     * @param int $storeId
     * @return void
     * @throws LocalizedException
     */
    public function preload(array $products, array $attributeCodes, int $storeId): void
    {
        foreach (array_unique($attributeCodes) as $code) {
            $attribute = $this->getTableAttribute($code);
            if ($attribute === null) {
                continue;
            }
            $ids = [];
            foreach ($products as $product) {
                foreach ($this->toOptionIds($product->getData($code)) as $id) {
                    $ids[$id] = $id;
                }
            }
            $this->loadLabels($attribute, $code, array_values($ids), $storeId);
        }
    }

    /**
     * Text value or null when empty / attribute does not exist
     *
     * @param ProductInterface $product
     * @param string $attributeCode
     * @param int $storeId
     * @return string|null
     * @throws LocalizedException
     */
    public function getValue(ProductInterface $product, string $attributeCode, int $storeId): ?string
    {
        $raw = $product->getData($attributeCode);
        if ($raw === null || $raw === '' || $raw === false || $raw === []) {
            return null;
        }
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
        if (!$attribute->getId()) {
            return null;
        }
        if (!$attribute->usesSource()) {
            return is_scalar($raw) ? (trim((string)$raw) ?: null) : null;
        }
        if (!$attribute->getSource() instanceof Table) {
            $attribute->setStoreId($storeId);
            $text = $attribute->getSource()->getOptionText($raw);
            $text = is_array($text) ? implode(', ', array_map('strval', $text)) : trim((string)$text);
            return $text !== '' ? $text : null;
        }

        $ids = $this->toOptionIds($raw);
        $this->loadLabels($attribute, $attributeCode, $ids, $storeId);
        $labels = [];
        foreach ($ids as $id) {
            $label = $this->labels[$attributeCode][$storeId][$id] ?? '';
            if ($label !== '') {
                $labels[] = $label;
            }
        }
        return $labels ? implode(', ', $labels) : null;
    }

    /**
     * Load labels of option ids that are not cached yet
     *
     * @param AbstractAttribute $attribute
     * @param string $code
     * @param string[] $ids
     * @param int $storeId
     * @return void
     */
    private function loadLabels(AbstractAttribute $attribute, string $code, array $ids, int $storeId): void
    {
        $missing = array_values(array_diff($ids, array_keys($this->labels[$code][$storeId] ?? [])));
        if (!$missing) {
            return;
        }
        foreach ($missing as $id) {
            $this->labels[$code][$storeId][$id] = '';
        }
        $attribute->setStoreId($storeId);
        /** @var Table $source */
        $source = $attribute->getSource();
        foreach ($source->getSpecificOptions($missing, false) as $option) {
            $this->labels[$code][$storeId][(string)$option['value']] = trim((string)$option['label']);
        }
    }

    /**
     * Attribute with a table source model, or null
     *
     * @param string $code
     * @return AbstractAttribute|null
     * @throws LocalizedException
     */
    private function getTableAttribute(string $code): ?AbstractAttribute
    {
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
        return $attribute->getId() && $attribute->usesSource() && $attribute->getSource() instanceof Table
            ? $attribute
            : null;
    }

    /**
     * Option ids of a select ("12") or multiselect ("12,15") value
     *
     * @param mixed $raw
     * @return string[]
     */
    private function toOptionIds(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return [];
        }
        $values = is_array($raw) ? $raw : explode(',', (string)$raw);
        return array_values(array_filter(array_map('trim', array_map('strval', $values)), 'strlen'));
    }
}
