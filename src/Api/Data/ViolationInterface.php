<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Api\Data;

interface ViolationInterface
{
    /** @return string */
    public function getCode(): string;

    /** @return string */
    public function getAttribute(): string;

    /** @return string */
    public function getMessage(): string;
}
