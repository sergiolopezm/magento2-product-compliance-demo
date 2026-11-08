<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Selopez\ProductCompliance\Model\ComplianceEnforcer;

class ValidateBeforeProductSave
{
    public function __construct(private readonly ComplianceEnforcer $enforcer)
    {
    }

    public function beforeSave(
        ProductRepositoryInterface $subject,
        ProductInterface $product,
        bool $saveOptions = false
    ): array {
        $this->enforcer->enforce($product);
        return [$product, $saveOptions];
    }
}
