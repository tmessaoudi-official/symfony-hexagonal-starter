<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Model\UserName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** PHPUnit class on purpose (Infection gates src/Domain); see EmailTest. */
final class UserNameTest extends TestCase
{
    #[Test]
    #[DataProvider('validNames')]
    public function itAcceptsAValidName(string $input, string $expected): void
    {
        self::assertSame($expected, UserName::fromString($input)->value());
    }

    /** @return iterable<string, array{string, string}> */
    public static function validNames(): iterable
    {
        yield 'simple name' => ['John Doe', 'John Doe'];
        yield 'international characters' => ['María García', 'María García'];
        yield 'hyphenated name' => ['Jean-Pierre', 'Jean-Pierre'];
        yield 'minimum: exactly 2 characters' => ['AB', 'AB'];
        yield 'maximum: exactly 100 characters' => [str_repeat('a', 100), str_repeat('a', 100)];
        yield 'leading and trailing whitespace is trimmed' => ['  Alice  ', 'Alice'];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function itRejectsAnInvalidName(string $input, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($expectedMessage);

        UserName::fromString($input);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidNames(): iterable
    {
        yield 'empty string' => ['', 'at least 2 characters'];
        yield 'too short (1 char)' => ['A', 'at least 2 characters'];
        yield 'whitespace only' => ['   ', 'at least 2 characters'];
        yield 'too long (101 chars)' => [str_repeat('a', 101), 'cannot exceed 100 characters'];
    }

    #[Test]
    public function itConsidersTwoUserNamesWithTheSameValueEqual(): void
    {
        self::assertTrue(UserName::fromString('Alice')->equals(UserName::fromString('Alice')));
    }

    #[Test]
    public function itConsidersTwoUserNamesWithDifferentValuesUnequal(): void
    {
        self::assertFalse(UserName::fromString('Alice')->equals(UserName::fromString('Bob')));
    }

    #[Test]
    public function itCastsToString(): void
    {
        self::assertSame('Alice Smith', (string) UserName::fromString('Alice Smith'));
    }
}
