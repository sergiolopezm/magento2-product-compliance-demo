<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Api\Data;

interface ComplianceResultInterface
{
    /** @return string */
    public function getSku(): string;

    /** @return bool */
    public function getEnabled(): bool;

    /** @return bool */
    public function getValid(): bool;

    /** @return \Selopez\ProductCompliance\Api\Data\ViolationInterface[] */
    public function getViolations(): array;
}
