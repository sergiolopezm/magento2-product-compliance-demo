<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddComplianceAttributes implements DataPatchInterface
{
    private const ATTRIBUTES = [
        'gtin' => ['varchar', 'GTIN'],
        'warranty_months' => ['int', 'Warranty (months)'],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $setup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        foreach (self::ATTRIBUTES as $code => $definition) {
            if ($setup->getAttributeId(Product::ENTITY, $code)) {
                throw new \LogicException('Compliance attribute already exists: ' . $code);
            }
        }
        foreach (self::ATTRIBUTES as $code => $definition) {
            $setup->addAttribute(Product::ENTITY, $code, [
                'type' => $definition[0],
                'label' => $definition[1],
                'input' => 'text',
                'required' => false,
                'user_defined' => true,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'group' => 'Product Details',
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'apply_to' => '',
            ]);
        }
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
