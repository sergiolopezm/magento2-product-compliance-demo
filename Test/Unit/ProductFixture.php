<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Selopez\ProductCompliance\Model\Config;

trait ProductFixture
{
    private function product(array $values = []): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('DEMO-TV-55');
        $product->method('getCustomAttribute')->willReturnCallback(function (string $code) use ($values) {
            if (!array_key_exists($code, $values)) {
                return null;
            }
            $attribute = $this->createMock(AttributeInterface::class);
            $attribute->method('getValue')->willReturn($values[$code]);
            return $attribute;
        });
        return $product;
    }

    private function config(array $overrides = [], bool $enabled = true): Config
    {
        $values = array_replace([
            'mode' => Config::MODE_BLOCK,
            'warranty_category_ids' => '12,34',
            'warranty_min_months' => '1',
            'warranty_max_months' => '120',
        ], $overrides);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->with('product_compliance/general/enabled')->willReturn($enabled);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[basename($path)] ?? null
        );
        return new Config($scopeConfig);
    }
}
