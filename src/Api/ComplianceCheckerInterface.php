<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Api;

use Magento\Catalog\Api\Data\ProductInterface;
use Selopez\ProductCompliance\Api\Data\ComplianceResultInterface;

interface ComplianceCheckerInterface
{
    /**
     * @param string $sku
     * @return \Selopez\ProductCompliance\Api\Data\ComplianceResultInterface
     */
    public function checkBySku(string $sku): ComplianceResultInterface;

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return \Selopez\ProductCompliance\Api\Data\ComplianceResultInterface
     */
    public function check(ProductInterface $product): ComplianceResultInterface;
}
