<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Selopez\ProductCompliance\Model\ComplianceEnforcer;

class ReleaseComplianceOnProductSave implements ObserverInterface
{
    public function __construct(private readonly ComplianceEnforcer $enforcer)
    {
    }

    public function execute(Observer $observer): void
    {
        $this->enforcer->release($observer->getEvent()->getData('product'));
    }
}
