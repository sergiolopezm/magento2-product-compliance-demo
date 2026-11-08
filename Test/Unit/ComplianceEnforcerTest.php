<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Selopez\ProductCompliance\Api\ComplianceCheckerInterface;
use Selopez\ProductCompliance\Model\ComplianceEnforcer;
use Selopez\ProductCompliance\Model\ComplianceResult;
use Selopez\ProductCompliance\Model\Config;
use Selopez\ProductCompliance\Model\Violation;
use Selopez\ProductCompliance\Observer\EnforceComplianceOnProductSave;
use Selopez\ProductCompliance\Observer\ReleaseComplianceOnProductSave;
use Selopez\ProductCompliance\Plugin\ValidateBeforeProductSave;

final class ComplianceEnforcerTest extends TestCase
{
    use ProductFixture;

    public function testBlockThrowsLocalizedExceptionWithoutLogging(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $enforcer = $this->enforcer($logger, Config::MODE_BLOCK, true, true);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Product compliance failed: Missing warranty');
        $enforcer->enforce($this->product());
    }

    public function testWarnLogsOnceForRepeatedEnforcement(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Product compliance validation failed.', [
            'sku' => 'DEMO-TV-55',
            'violations' => [[
                'code' => 'warranty_required',
                'attribute' => 'warranty_months',
                'message' => 'Missing warranty',
            ]],
        ]);
        $enforcer = $this->enforcer($logger, Config::MODE_WARN, true, true);
        $product = $this->product();
        $enforcer->enforce($product);
        $enforcer->enforce($product);
    }

    #[DataProvider('bypassCases')]
    public function testValidOrDisabledProductIsCheckedOnlyOnce(bool $enabled, bool $invalid): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $enforcer = $this->enforcer($logger, Config::MODE_BLOCK, $enabled, $invalid);
        $product = $this->product();
        $enforcer->enforce($product);
        $enforcer->enforce($product);
    }

    public static function bypassCases(): array
    {
        return [[true, false], [false, false], [false, true]];
    }

    public function testTransientFlagSurvivesRepositoryDataCopyAndSkipsObserver(): void
    {
        $product = $this->dataProduct();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $enforcer = $this->enforcer($logger, Config::MODE_WARN, true, true);
        $plugin = new ValidateBeforeProductSave($enforcer);
        self::assertSame([$product, false], $plugin->beforeSave(
            $this->createMock(ProductRepositoryInterface::class),
            $product
        ));
        self::assertTrue($product->getData('_selopez_compliance_checked'));
        $persistedProduct = $this->dataProduct();
        $persistedProduct->setData($product->getData());
        (new EnforceComplianceOnProductSave($enforcer))->execute(new Observer([
            'event' => new Event(['product' => $persistedProduct]),
        ]));
    }

    public function testBlockedProductRemainsUnmarkedAndIsCheckedAgain(): void
    {
        $checker = $this->createMock(ComplianceCheckerInterface::class);
        $checker->expects(self::exactly(2))->method('check')->willReturn(new ComplianceResult(
            'DEMO-TV-55',
            true,
            [new Violation('warranty_required', 'warranty_months', 'Missing warranty')]
        ));
        $enforcer = new ComplianceEnforcer($checker, $this->config(), $this->createMock(LoggerInterface::class));
        $product = $this->dataProduct();
        foreach ([1, 2] as $attempt) {
            try {
                $enforcer->enforce($product);
                self::fail('Expected compliance enforcement to block attempt ' . $attempt);
            } catch (LocalizedException $exception) {
                self::assertStringContainsString('Missing warranty', $exception->getMessage());
                self::assertNull($product->getData('_selopez_compliance_checked'));
            }
        }
    }

    public function testDifferentProductInstancesAreCheckedIndependently(): void
    {
        $checker = $this->createMock(ComplianceCheckerInterface::class);
        $checker->expects(self::exactly(2))->method('check')->willReturn(new ComplianceResult('DEMO-TV-55', true));
        $enforcer = new ComplianceEnforcer($checker, $this->config(), $this->createMock(LoggerInterface::class));
        $enforcer->enforce($this->product());
        $enforcer->enforce($this->product());
    }

    #[DataProvider('savedProductInstances')]
    public function testSaveAfterReleasesProductForModifiedSave(bool $copyProduct): void
    {
        $product = $this->dataProduct();
        $product->setData('sku', 'DEMO-TV-55');
        $product->setData('warranty_months', 24);
        $checker = $this->createMock(ComplianceCheckerInterface::class);
        $checker->expects(self::exactly(2))->method('check')->with($product)->willReturnCallback(
            static fn (Product $savedProduct): ComplianceResult => new ComplianceResult(
                'DEMO-TV-55',
                true,
                $savedProduct->getData('warranty_months') === 0
                    ? [new Violation('warranty_range', 'warranty_months', 'Invalid warranty')]
                    : []
            )
        );
        $enforcer = new ComplianceEnforcer($checker, $this->config(), $this->createMock(LoggerInterface::class));
        $enforcer->enforce($product);
        $persistedProduct = $copyProduct ? $this->dataProduct() : $product;
        $persistedProduct->setData($product->getData());
        (new ReleaseComplianceOnProductSave($enforcer))->execute(new Observer([
            'event' => new Event(['product' => $persistedProduct]),
        ]));
        self::assertNull($persistedProduct->getData('_selopez_compliance_checked'));
        self::assertNull($product->getData('_selopez_compliance_checked'));
        $product->setData('warranty_months', 0);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Invalid warranty');
        $enforcer->enforce($product);
    }

    public static function savedProductInstances(): array
    {
        return [[false], [true]];
    }

    public function testReleaseClearsWeakMapAndCanBeRepeated(): void
    {
        $product = $this->product();
        $checker = $this->createMock(ComplianceCheckerInterface::class);
        $checker->expects(self::exactly(2))->method('check')->with($product)
            ->willReturn(new ComplianceResult('DEMO-TV-55', true));
        $enforcer = new ComplianceEnforcer($checker, $this->config(), $this->createMock(LoggerInterface::class));
        $enforcer->enforce($product);
        $enforcer->enforce($product);
        $enforcer->release($product);
        $enforcer->release($product);
        $enforcer->enforce($product);
    }

    public function testReleaseKeepsOtherProductMarked(): void
    {
        $savedProduct = $this->dataProduct();
        $savedProduct->setData('sku', 'DEMO-TV-55');
        $otherProduct = $this->dataProduct();
        $otherProduct->setData('sku', 'DEMO-LAMP-01');
        $checker = $this->createMock(ComplianceCheckerInterface::class);
        $checker->expects(self::exactly(2))->method('check')->willReturn(new ComplianceResult('DEMO-TV-55', true));
        $enforcer = new ComplianceEnforcer($checker, $this->config(), $this->createMock(LoggerInterface::class));
        $enforcer->enforce($savedProduct);
        $enforcer->enforce($otherProduct);
        $enforcer->release($savedProduct);
        self::assertNull($savedProduct->getData('_selopez_compliance_checked'));
        self::assertTrue($otherProduct->getData('_selopez_compliance_checked'));
        $enforcer->enforce($otherProduct);
    }

    private function dataProduct(): Product
    {
        return $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
    }

    private function enforcer(
        LoggerInterface $logger,
        string $mode,
        bool $enabled,
        bool $invalid
    ): ComplianceEnforcer {
        $checker = $this->createMock(ComplianceCheckerInterface::class);
        $checker->expects(self::once())->method('check')->willReturn(new ComplianceResult(
            'DEMO-TV-55',
            $enabled,
            $invalid ? [new Violation('warranty_required', 'warranty_months', 'Missing warranty')] : []
        ));
        return new ComplianceEnforcer($checker, $this->config(['mode' => $mode]), $logger);
    }
}
