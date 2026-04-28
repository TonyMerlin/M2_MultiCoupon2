<?php
declare(strict_types=1);

namespace Merlin\MultiCoupon\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SeedDefaultCodes implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $tableName = $this->resourceConnection->getTableName('merlin_multicoupon_code');

        $rows = [
            [
                'code' => 'DEAL5',
                'label' => 'Extra 5% OFF',
                'is_active' => 1,
                'sort_order' => 10,
            ],
            [
                'code' => 'DEAL10',
                'label' => 'Extra 10% OFF',
                'is_active' => 1,
                'sort_order' => 20,
            ],
            [
                'code' => 'DEAL15',
                'label' => 'Extra 15% OFF',
                'is_active' => 1,
                'sort_order' => 30,
            ],
            [
                'code' => 'DEAL20',
                'label' => 'Extra 20% OFF',
                'is_active' => 1,
                'sort_order' => 40,
            ],
            [
                'code' => 'DEAL25',
                'label' => 'Extra 25% OFF',
                'is_active' => 1,
                'sort_order' => 50,
            ],
        ];

        foreach ($rows as $row) {
            $connection->insertOnDuplicate(
                $tableName,
                $row,
                ['label', 'is_active', 'sort_order']
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
