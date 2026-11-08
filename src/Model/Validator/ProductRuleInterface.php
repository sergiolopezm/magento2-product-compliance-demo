<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model\Validator;

use Magento\Catalog\Api\Data\ProductInterface;

interface ProductRuleInterface
{
    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return \Selopez\ProductCompliance\Api\Data\ViolationInterface[]
     */
    public function validate(ProductInterface $product): array;
}
