<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit\Plugin;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\ComplianceEnforcer;
use Selopez\ProductCompliance\Plugin\ValidateBeforeProductSave;
use Selopez\ProductCompliance\Test\Unit\ProductFixture;

final class ValidateBeforeProductSaveTest extends TestCase
{
    use ProductFixture;

    #[DataProvider('saveOptions')]
    public function testDelegatesAndPreservesArguments(bool $saveOptions): void
    {
        $product = $this->product();
        $enforcer = $this->createMock(ComplianceEnforcer::class);
        $enforcer->expects(self::once())->method('enforce')->with($product);
        $plugin = new ValidateBeforeProductSave($enforcer);
        self::assertSame([$product, $saveOptions], $plugin->beforeSave(
            $this->createMock(ProductRepositoryInterface::class),
            $product,
            $saveOptions
        ));
    }

    public static function saveOptions(): array
    {
        return [[false], [true]];
    }

    public function testPropagatesEnforcementFailure(): void
    {
        $enforcer = $this->createMock(ComplianceEnforcer::class);
        $enforcer->method('enforce')->willThrowException(new LocalizedException(new Phrase('Invalid product')));
        $this->expectException(LocalizedException::class);
        (new ValidateBeforeProductSave($enforcer))->beforeSave(
            $this->createMock(ProductRepositoryInterface::class),
            $this->product()
        );
    }
}
