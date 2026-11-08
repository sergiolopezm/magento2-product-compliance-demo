<?php

declare(strict_types=1);

namespace Selopez\ProductCompliance\Test\Unit\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Selopez\ProductCompliance\Model\Validator\GtinValidator;
use Selopez\ProductCompliance\Test\Unit\ProductFixture;

final class GtinValidatorTest extends TestCase
{
    use ProductFixture;

    #[DataProvider('validGtins')]
    public function testAcceptsValidOrMissingGtin(mixed $value): void
    {
        self::assertSame([], (new GtinValidator())->validate($this->product(['gtin' => $value])));
    }

    public static function validGtins(): array
    {
        return [
            ['96385074'], ['036000291452'], ['4006381333931'], ['10012345000017'],
            ['00012345600012'], ['00000000'], [''], [null],
        ];
    }

    #[DataProvider('invalidGtins')]
    public function testRejectsMalformedOrIncorrectGtin(mixed $value, string $code): void
    {
        $violations = (new GtinValidator())->validate($this->product(['gtin' => $value]));
        self::assertCount(1, $violations);
        self::assertSame($code, $violations[0]->getCode());
        self::assertSame('gtin', $violations[0]->getAttribute());
        self::assertNotSame('', $violations[0]->getMessage());
    }

    public static function invalidGtins(): array
    {
        return [
            ['96385075', 'gtin_checksum'], ['036000291453', 'gtin_checksum'],
            ['4006381333932', 'gtin_checksum'], ['10012345000018', 'gtin_checksum'],
            ['1234567', 'gtin_format'], ['123456789', 'gtin_format'],
            ['12345678901', 'gtin_format'], ['123456789012345', 'gtin_format'],
            ['9638A074', 'gtin_format'], [' 96385074', 'gtin_format'],
            ["96385074\n", 'gtin_format'], ['９６３８５０７４', 'gtin_format'],
            [96385074, 'gtin_format'], [false, 'gtin_format'], [[], 'gtin_format'],
        ];
    }

    public function testAcceptsAbsentAttribute(): void
    {
        self::assertSame([], (new GtinValidator())->validate($this->product()));
    }
}
