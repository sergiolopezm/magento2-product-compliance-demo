<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

class Config
{
    public const MODE_BLOCK = 'block';
    public const MODE_WARN = 'warn';
    private const PREFIX = 'product_compliance/general/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'enabled');
    }

    public function getMode(): string
    {
        $mode = (string) $this->scopeConfig->getValue(self::PREFIX . 'mode');
        if (!in_array($mode, [self::MODE_BLOCK, self::MODE_WARN], true)) {
            throw new LocalizedException(new Phrase('Invalid product compliance mode.'));
        }
        return $mode;
    }

    public function getWarrantyCategoryIds(): array
    {
        $value = (string) $this->scopeConfig->getValue(self::PREFIX . 'warranty_category_ids');
        if (trim($value) === '') {
            return [];
        }
        $ids = array_map('trim', explode(',', $value));
        foreach ($ids as $id) {
            if (!ctype_digit($id) || (int) $id < 1) {
                throw new LocalizedException(new Phrase('Warranty category IDs must be positive integers.'));
            }
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function getWarrantyBounds(): array
    {
        $minimum = $this->getMonths('warranty_min_months');
        $maximum = $this->getMonths('warranty_max_months');
        if ($minimum > $maximum) {
            throw new LocalizedException(new Phrase('Warranty minimum cannot exceed its maximum.'));
        }
        return [$minimum, $maximum];
    }

    private function getMonths(string $field): int
    {
        $value = $this->scopeConfig->getValue(self::PREFIX . $field);
        $months = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($months === false || $value === null || is_bool($value)) {
            throw new LocalizedException(new Phrase('Warranty bounds must be non-negative integers.'));
        }
        return $months;
    }
}
