<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\ComplianceEnforcer;
use Selopez\ProductCompliance\Observer\ReleaseComplianceOnProductSave;
use Selopez\ProductCompliance\Test\Unit\ProductFixture;

final class ReleaseComplianceOnProductSaveTest extends TestCase
{
    use ProductFixture;

    public function testDelegatesEventProductToRelease(): void
    {
        $product = $this->product();
        $enforcer = $this->createMock(ComplianceEnforcer::class);
        $enforcer->expects(self::once())->method('release')->with($product);
        (new ReleaseComplianceOnProductSave($enforcer))->execute(new Observer([
            'event' => new Event(['product' => $product]),
        ]));
    }
}
