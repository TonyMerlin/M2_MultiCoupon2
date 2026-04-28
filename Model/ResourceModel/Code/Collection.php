<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\ResourceModel\Code;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Merlin\MultiCoupon\Model\Code as Model;
use Merlin\MultiCoupon\Model\ResourceModel\Code as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
