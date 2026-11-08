<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Selopez\ProductCompliance\Api\ComplianceCheckerInterface;
use Selopez\ProductCompliance\Api\Data\ComplianceResultInterface;

class ComplianceChecker implements ComplianceCheckerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Config $config,
        private readonly array $rules = []
    ) {
    }

    public function checkBySku(string $sku): ComplianceResultInterface
    {
        return $this->check($this->productRepository->get($sku));
    }

    public function check(ProductInterface $product): ComplianceResultInterface
    {
        $enabled = $this->config->isEnabled();
        $violations = [];
        if ($enabled) {
            foreach ($this->rules as $rule) {
                array_push($violations, ...$rule->validate($product));
            }
        }
        return new ComplianceResult((string) $product->getSku(), $enabled, $violations);
    }
}
