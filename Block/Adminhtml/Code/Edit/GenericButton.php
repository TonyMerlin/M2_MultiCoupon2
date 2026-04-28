<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Block\Adminhtml\Code\Edit;

use Magento\Backend\Block\Widget\Context;

class GenericButton
{
    /**
     * @param Context $context
     */
    public function __construct(
        protected Context $context
    ) {
    }

    /**
     * Return current entity ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        $id = (int)$this->context->getRequest()->getParam('entity_id');
        return $id > 0 ? $id : null;
    }

    /**
     * Build backend URL.
     *
     * @param string $route
     * @param array<string, mixed> $params
     * @return string
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
