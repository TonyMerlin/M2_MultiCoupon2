<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Code extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('merlin_multicoupon_code', 'entity_id');
    }
}
