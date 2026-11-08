<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\CategoryIdsResolver;
use Selopez\ProductCompliance\Model\Config;
use Selopez\ProductCompliance\Model\Validator\WarrantyValidator;
use Selopez\ProductCompliance\Test\Unit\ProductFixture;

final class WarrantyValidatorTest extends TestCase
{
    use ProductFixture;

    #[DataProvider('warranties')]
    public function testValidatesCategoriesAndInclusiveBounds(
        array $categories,
        mixed $months,
        ?string $expectedCode
    ): void {
        $validator = new WarrantyValidator($this->config(), new CategoryIdsResolver());
        $violations = $validator->validate($this->product([
            'category_ids' => $categories,
            'warranty_months' => $months,
        ]));
        self::assertCount($expectedCode === null ? 0 : 1, $violations);
        if ($expectedCode !== null) {
            self::assertSame($expectedCode, $violations[0]->getCode());
            self::assertSame('warranty_months', $violations[0]->getAttribute());
        }
    }

    public static function warranties(): array
    {
        return [
            [[12], null, 'warranty_required'], [['34'], '', 'warranty_required'],
            [[8, 12], null, 'warranty_required'], [[8], null, null], [[], '', null],
            [[12], 1, null], [[12], 120, null], [[12], '24', null],
            [[12], 0, 'warranty_range'], [[12], 121, 'warranty_range'],
            [[12], -1, 'warranty_range'], [[12], 1.5, 'warranty_range'],
            [[12], '1.0', 'warranty_range'], [[12], '12e1', 'warranty_range'],
            [[12], ' 12', 'warranty_range'], [[12], true, 'warranty_range'],
            [[12], [], 'warranty_range'], [[12], '99999999999999999999999', 'warranty_range'],
            [[8], 121, 'warranty_range'], [[8], 'invalid', 'warranty_range'],
            [[12], '001', null],
        ];
    }

    #[DataProvider('enforcementModes')]
    public function testModeDoesNotChangeRuleResult(string $mode): void
    {
        $validator = new WarrantyValidator($this->config(['mode' => $mode]), new CategoryIdsResolver());
        self::assertSame('warranty_required', $validator->validate($this->product([
            'category_ids' => [12],
        ]))[0]->getCode());
    }

    public static function enforcementModes(): array
    {
        return [[Config::MODE_BLOCK], [Config::MODE_WARN]];
    }

    public function testEmptyConfiguredCategoriesDoNotRequireWarranty(): void
    {
        $validator = new WarrantyValidator(
            $this->config(['warranty_category_ids' => '']),
            new CategoryIdsResolver()
        );
        self::assertSame([], $validator->validate($this->product(['category_ids' => [12]])));
    }

    public function testCustomBoundsAllowZero(): void
    {
        $validator = new WarrantyValidator(
            $this->config(['warranty_min_months' => '0', 'warranty_max_months' => '0']),
            new CategoryIdsResolver()
        );
        self::assertSame([], $validator->validate($this->product(['warranty_months' => 0])));
    }
}
