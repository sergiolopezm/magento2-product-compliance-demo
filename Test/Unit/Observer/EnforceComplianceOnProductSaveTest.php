<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\ComplianceEnforcer;
use Selopez\ProductCompliance\Observer\EnforceComplianceOnProductSave;
use Selopez\ProductCompliance\Test\Unit\ProductFixture;

final class EnforceComplianceOnProductSaveTest extends TestCase
{
    use ProductFixture;

    public function testDelegatesEventProductToEnforcer(): void
    {
        $product = $this->product();
        $enforcer = $this->createMock(ComplianceEnforcer::class);
        $enforcer->expects(self::once())->method('enforce')->with($product);
        $observer = new EnforceComplianceOnProductSave($enforcer);
        $observer->execute(new Observer(['event' => new Event(['product' => $product])]));
    }

    public function testPropagatesEnforcementFailure(): void
    {
        $enforcer = $this->createMock(ComplianceEnforcer::class);
        $enforcer->method('enforce')->willThrowException(new LocalizedException(new Phrase('Invalid product')));
        $this->expectException(LocalizedException::class);
        (new EnforceComplianceOnProductSave($enforcer))->execute(new Observer([
            'event' => new Event(['product' => $this->product()]),
        ]));
    }
}
