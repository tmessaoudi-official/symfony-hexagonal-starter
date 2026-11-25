<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Model\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Domain tests are plain PHPUnit classes (not Pest closures) on purpose: Infection, which gates
 * mutation testing for src/Domain, can only drive PHPUnit test classes. Pest runs them as-is.
 * See docs/testing.md ("Mutation testing: two tools, one per layer").
 */
final class EmailTest extends TestCase
{
    #[Test]
    public function itCreatesAValidEmailAndNormalisesToLowercase(): void
    {
        self::assertSame('user@example.com', Email::fromString('User@EXAMPLE.COM')->value());
    }

    #[Test]
    public function itTrimsSurroundingWhitespace(): void
    {
        self::assertSame('user@example.com', Email::fromString('  user@example.com  ')->value());
    }

    #[Test]
    public function itExtractsTheDomainPart(): void
    {
        self::assertSame('example.org', Email::fromString('alice@example.org')->domain());
    }

    #[Test]
    public function itConsidersTwoEmailsWithTheSameValueEqual(): void
    {
        self::assertTrue(Email::fromString('alice@example.com')->equals(Email::fromString('Alice@EXAMPLE.COM')));
    }

    #[Test]
    public function itConsidersTwoEmailsWithDifferentValuesUnequal(): void
    {
        self::assertFalse(Email::fromString('alice@example.com')->equals(Email::fromString('bob@example.com')));
    }

    #[Test]
    public function itThrowsOnAnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('cannot be empty');

        Email::fromString('');
    }

    #[Test]
    public function itThrowsOnAStringWithoutAt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('not a valid email');

        Email::fromString('notanemail');
    }

    #[Test]
    public function itAcceptsAnEmailOfExactly254Characters(): void
    {
        // Boundary of the length rule (RFC 5321). Found by mutation testing: without this
        // test, changing "> 254" to ">= 254" in Email went unnoticed.
        $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.com';

        self::assertSame(254, strlen($email));
        self::assertSame($email, Email::fromString($email)->value());
    }

    #[Test]
    public function itThrowsOnAnEmailExceeding254Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('maximum length');

        Email::fromString(str_repeat('a', 243).'@example.com'); // 255 chars
    }

    #[Test]
    public function itCastsToString(): void
    {
        self::assertSame('alice@example.com', (string) Email::fromString('alice@example.com'));
    }

    #[Test]
    public function itAcceptsPlusTaggedEmailAddresses(): void
    {
        self::assertSame('user+tag@domain.co.uk', Email::fromString('user+tag@domain.co.uk')->value());
    }

    #[Test]
    public function itAcceptsADottedLocalPartWithSubdomain(): void
    {
        self::assertSame('user.name@sub.domain.org', Email::fromString('user.name@sub.domain.org')->value());
    }

    #[Test]
    public function itThrowsOnAnEmailWithNoDomainAfterAt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('not a valid email');

        Email::fromString('user@');
    }

    #[Test]
    public function itThrowsOnAnEmailWithSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('not a valid email');

        Email::fromString('user @example.com');
    }

    #[Test]
    public function itThrowsOnAnEmailWithDoubleAtSign(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('not a valid email');

        Email::fromString('user@@example.com');
    }
}
