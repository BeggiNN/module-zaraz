<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper\Modifier\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Perspective\Zaraz\Api\ProductDataModifierInterface;
use Perspective\Zaraz\Model\Config;

/**
 * "On sale" flag: the final price (special price, catalog rule, tier for qty 1) is lower than the regular price
 */
class OnSale implements ProductDataModifierInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function modify(array $data, ProductInterface $product, ?ProductInterface $child, int $storeId): array
    {
        $key = $this->config->getProductPropertyKey('on_sale', $storeId);
        if ($key === null) {
            return $data;
        }
        $priceInfo = ($child ?? $product)->getPriceInfo();
        $final = (float)$priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount();
        $regular = (float)$priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getBaseAmount();
        $data[$key] = $final > 0 && $final < $regular;
        return $data;
    }
}
