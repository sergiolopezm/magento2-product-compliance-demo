<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\ComplianceChecker;
use Selopez\ProductCompliance\Model\Validator\ProductRuleInterface;
use Selopez\ProductCompliance\Model\Violation;

final class ComplianceCheckerTest extends TestCase
{
    use ProductFixture;

    public function testAggregatesAllRulesInPoolOrder(): void
    {
        $product = $this->product();
        $first = new Violation('gtin_format', 'gtin', 'Invalid GTIN');
        $second = new Violation('warranty_required', 'warranty_months', 'Missing warranty');
        $rules = [];
        foreach ([[$first], [], [$second]] as $violations) {
            $rule = $this->createMock(ProductRuleInterface::class);
            $rule->expects(self::once())->method('validate')->with($product)->willReturn($violations);
            $rules[] = $rule;
        }
        $checker = new ComplianceChecker(
            $this->createMock(ProductRepositoryInterface::class),
            $this->config(),
            $rules
        );
        $result = $checker->check($product);
        self::assertSame('DEMO-TV-55', $result->getSku());
        self::assertTrue($result->getEnabled());
        self::assertFalse($result->getValid());
        self::assertSame([$first, $second], $result->getViolations());
    }

    public function testLoadsProductThroughRepositoryBySku(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects(self::once())->method('get')->with('DEMO-TV-55')->willReturn($this->product());
        $result = (new ComplianceChecker($repository, $this->config()))->checkBySku('DEMO-TV-55');
        self::assertTrue($result->getValid());
        self::assertSame([], $result->getViolations());
    }

    public function testDisabledCheckerDoesNotInvokeRules(): void
    {
        $rule = $this->createMock(ProductRuleInterface::class);
        $rule->expects(self::never())->method('validate');
        $checker = new ComplianceChecker(
            $this->createMock(ProductRepositoryInterface::class),
            $this->config(enabled: false),
            [$rule]
        );
        $result = $checker->check($this->product());
        self::assertFalse($result->getEnabled());
        self::assertTrue($result->getValid());
    }

    public function testPropagatesUnknownSku(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('get')->willThrowException(new NoSuchEntityException(new Phrase('Unknown SKU')));
        $this->expectException(NoSuchEntityException::class);
        (new ComplianceChecker($repository, $this->config()))->checkBySku('MISSING');
    }
}
