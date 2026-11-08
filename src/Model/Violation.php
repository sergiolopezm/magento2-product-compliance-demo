<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Model;

use Selopez\ProductCompliance\Api\Data\ViolationInterface;

class Violation implements ViolationInterface
{
    public function __construct(
        private readonly string $code,
        private readonly string $attribute,
        private readonly string $message
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
