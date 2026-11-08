<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\Config;

final class ConfigTest extends TestCase
{
    use ProductFixture;

    public function testReadsDefaultsAndNormalizesCategories(): void
    {
        $config = $this->config(['warranty_category_ids' => '12, 34,12']);
        self::assertTrue($config->isEnabled());
        self::assertSame(Config::MODE_BLOCK, $config->getMode());
        self::assertSame([12, 34], $config->getWarrantyCategoryIds());
        self::assertSame([1, 120], $config->getWarrantyBounds());
    }

    public function testReadsWarnModeAndEmptyCategories(): void
    {
        $config = $this->config(['mode' => Config::MODE_WARN, 'warranty_category_ids' => '  '], false);
        self::assertFalse($config->isEnabled());
        self::assertSame(Config::MODE_WARN, $config->getMode());
        self::assertSame([], $config->getWarrantyCategoryIds());
    }

    public function testRejectsInvalidMode(): void
    {
        $this->expectException(LocalizedException::class);
        $this->config(['mode' => 'invalid'])->getMode();
    }

    #[DataProvider('invalidCategoryIds')]
    public function testRejectsInvalidCategoryIds(string $value): void
    {
        $this->expectException(LocalizedException::class);
        $this->config(['warranty_category_ids' => $value])->getWarrantyCategoryIds();
    }

    public static function invalidCategoryIds(): array
    {
        return [['0'], ['-12'], ['12,'], ['abc'], ['1.5']];
    }

    #[DataProvider('invalidBounds')]
    public function testRejectsInvalidBounds(mixed $minimum, mixed $maximum): void
    {
        $this->expectException(LocalizedException::class);
        $this->config(['warranty_min_months' => $minimum, 'warranty_max_months' => $maximum])->getWarrantyBounds();
    }

    public static function invalidBounds(): array
    {
        return [['121', '120'], ['-1', '120'], ['1.5', '120'], [null, '120'], [true, '120'], ['1', 'invalid']];
    }
}
