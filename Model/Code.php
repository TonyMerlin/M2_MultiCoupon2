<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model;

use Magento\Framework\Model\AbstractModel;
use Merlin\MultiCoupon\Model\ResourceModel\Code as ResourceModel;

class Code extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}
