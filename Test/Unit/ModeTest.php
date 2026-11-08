<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit;

use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\Config;
use Selopez\ProductCompliance\Model\Config\Source\Mode;

final class ModeTest extends TestCase
{
    public function testProvidesBothEnforcementModes(): void
    {
        $options = (new Mode())->toOptionArray();
        self::assertSame([Config::MODE_BLOCK, Config::MODE_WARN], array_column($options, 'value'));
        self::assertSame('Block save', (string) $options[0]['label']);
        self::assertSame('Log warning', (string) $options[1]['label']);
    }
}
