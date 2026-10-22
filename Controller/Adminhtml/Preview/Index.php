<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Controller\Adminhtml\Preview;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Perspective_Zaraz::preview';

    /**
     * @inheritDoc
     */
    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Perspective_Zaraz::preview');
        $page->getConfig()->getTitle()->prepend(__('Cloudflare Zaraz: Preview Payload'));
        return $page;
    }
}
