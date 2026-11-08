<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model;

use Selopez\ProductCompliance\Api\Data\ComplianceResultInterface;

class ComplianceResult implements ComplianceResultInterface
{
    public function __construct(
        private readonly string $sku,
        private readonly bool $enabled,
        private readonly array $violations = []
    ) {
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function getValid(): bool
    {
        return $this->violations === [];
    }

    public function getViolations(): array
    {
        return $this->violations;
    }
}
