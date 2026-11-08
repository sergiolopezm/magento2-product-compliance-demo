<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model\Validator;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Phrase;
use Selopez\ProductCompliance\Model\CategoryIdsResolver;
use Selopez\ProductCompliance\Model\Config;
use Selopez\ProductCompliance\Model\Violation;

class WarrantyValidator implements ProductRuleInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CategoryIdsResolver $categoryIdsResolver
    ) {
    }

    public function validate(ProductInterface $product): array
    {
        $required = array_intersect(
            $this->categoryIdsResolver->resolve($product),
            $this->config->getWarrantyCategoryIds()
        );
        $value = $product->getCustomAttribute('warranty_months')?->getValue();
        if ($value === null || $value === '') {
            return $required === [] ? [] : [new Violation(
                'warranty_required',
                'warranty_months',
                (string) new Phrase('Warranty months are required for this product category.')
            )];
        }
        [$minimum, $maximum] = $this->config->getWarrantyBounds();
        $isInteger = is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1);
        if (!$isInteger || $value < $minimum || $value > $maximum) {
            return [new Violation('warranty_range', 'warranty_months', (string) new Phrase(
                'Warranty months must be an integer between %1 and %2.',
                [$minimum, $maximum]
            ))];
        }
        return [];
    }
}
