<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model\Validator;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Phrase;
use Selopez\ProductCompliance\Model\Violation;

class GtinValidator implements ProductRuleInterface
{
    private const LENGTHS = [8, 12, 13, 14];
    private const CHECKSUM_MODULUS = 10;
    private const ALTERNATING_WEIGHT = 3;

    public function validate(ProductInterface $product): array
    {
        $value = $product->getCustomAttribute('gtin')?->getValue();
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_string($value) || !ctype_digit($value) || !in_array(strlen($value), self::LENGTHS, true)) {
            return [new Violation('gtin_format', 'gtin', (string) new Phrase(
                'GTIN must contain 8, 12, 13 or 14 digits.'
            ))];
        }
        $sum = 0;
        $weight = self::ALTERNATING_WEIGHT;
        for ($index = strlen($value) - 2; $index >= 0; $index--) {
            $sum += (int) $value[$index] * $weight;
            $weight = $weight === self::ALTERNATING_WEIGHT ? 1 : self::ALTERNATING_WEIGHT;
        }
        $expected = (self::CHECKSUM_MODULUS - $sum % self::CHECKSUM_MODULUS) % self::CHECKSUM_MODULUS;
        if ($expected !== (int) $value[strlen($value) - 1]) {
            return [new Violation('gtin_checksum', 'gtin', (string) new Phrase('GTIN check digit is invalid.'))];
        }
        return [];
    }
}
