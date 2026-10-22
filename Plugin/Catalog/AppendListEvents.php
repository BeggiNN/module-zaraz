<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Plugin\Catalog;

use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Api\EventGeneratorInterface;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Event\ContextFactory;
use Perspective\Zaraz\Model\Event\EventCode;
use Perspective\Zaraz\Model\Mapper\ProductLoader;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\ProductList\ResolverPool;
use Psr\Log\LoggerInterface;

/**
 * Appends to a product list block:
 * - "Product List Viewed" events, split into chunks of the configured size;
 * - "Product Clicked" templates keyed by product id (the JS adapter sends one when a product link is clicked; the list
 *   of a clicked product is the nearest following list script, because the script is rendered right after the list).
 *
 * Data is public (catalog prices of the page variant), so the output is safe for FPC and block cache.
 */
class AppendListEvents
{
    /**
     * @param Config $config
     * @param ResolverPool $resolverPool
     * @param EventGeneratorInterface $generator
     * @param ContextFactory $contextFactory
     * @param ProductLoader $productLoader
     * @param ProductMapper $productMapper
     * @param StoreManagerInterface $storeManager
     * @param SecureHtmlRenderer $secureRenderer
     * @param JsonHexTag $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly ResolverPool $resolverPool,
        private readonly EventGeneratorInterface $generator,
        private readonly ContextFactory $contextFactory,
        private readonly ProductLoader $productLoader,
        private readonly ProductMapper $productMapper,
        private readonly StoreManagerInterface $storeManager,
        private readonly SecureHtmlRenderer $secureRenderer,
        private readonly JsonHexTag $json,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Append list events after the block HTML
     *
     * @param AbstractBlock $subject
     * @param string $html
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterToHtml(AbstractBlock $subject, $html)
    {
        if (!is_string($html) || trim($html) === '') {
            return $html;
        }
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            if (!$this->config->isEnabled($storeId)) {
                return $html;
            }
            $list = $this->resolverPool->resolve($subject);
            return $list ? $html . $this->render($list, $storeId) : $html;
        } catch (\Throwable $e) {
            // Tracking must never break the listing
            $this->logger->error('Perspective_Zaraz: list events failed: ' . $e->getMessage(), ['exception' => $e]);
            return $html;
        }
    }

    /**
     * Events and click templates of the list
     *
     * @param array $list
     * @param int $storeId
     * @return string
     */
    private function render(array $list, int $storeId): string
    {
        $products = array_values($list['products']);
        $ids = [];
        foreach ($products as $product) {
            // Listing items already carry price index and salable data: map them without reloading
            $this->productLoader->register($product, $storeId);
            $ids[] = (int)$product->getId();
        }
        // Attributes, categories, reviews, option labels of the whole list at once (events are built in parts)
        $this->productMapper->preload($ids, $storeId);
        $listData = ['list_id' => (string)$list['id'], 'list_name' => (string)$list['name']];
        $offset = (int)($list['offset'] ?? 0);

        $events = [];
        foreach (array_chunk($products, $this->config->getListChunkSize($storeId)) as $index => $chunk) {
            $event = $this->generator->generate(EventCode::PRODUCT_LIST_VIEWED, $this->contextFactory->create([
                'storeId' => $storeId,
                'data' => $listData + [
                    'products' => $chunk,
                    'offset' => $offset + $index * $this->config->getListChunkSize($storeId),
                ],
            ]));
            if ($event !== null) {
                $events[] = $event->toArray();
            }
        }

        $clicks = [];
        foreach ($products as $index => $product) {
            $event = $this->generator->generate(EventCode::PRODUCT_CLICKED, $this->contextFactory->create([
                'storeId' => $storeId,
                'product' => $product,
                'data' => $listData + ['position' => $offset + $index + 1],
            ]));
            if ($event === null) {
                break;
            }
            $clicks[(string)$product->getId()] = $event->toArray();
        }

        $output = '';
        if ($events) {
            $output .= $this->script('events', $this->json->serialize($events));
        }
        if ($clicks) {
            $output .= $this->script('list', $this->json->serialize($listData + ['clicks' => $clicks]));
        }
        return $output;
    }

    /**
     * JSON script tag
     *
     * @param string $type
     * @param string $json
     * @return string
     */
    private function script(string $type, string $json): string
    {
        return $this->secureRenderer->renderTag(
            'script',
            ['type' => 'application/json', 'data-perspective-zaraz' => $type],
            $json,
            false
        );
    }
}
