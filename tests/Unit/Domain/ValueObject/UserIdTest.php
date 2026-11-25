<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Model\UserId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** PHPUnit class on purpose (Infection gates src/Domain); see EmailTest. */
final class UserIdTest extends TestCase
{
    private const string UUID = '550e8400-e29b-41d4-a716-446655440000';

    #[Test]
    public function itGeneratesAValidUuidV4(): void
    {
        // 256 ids, not one: generate() starts from random bytes, and a bug in the version or
        // variant bit masking shows up only for some byte values (in about half of them for
        // most masking errors). One sample let such a mutant survive about one run in two;
        // with 256 samples the chance is below 2^-256, so the test is deterministic in practice.
        for ($i = 0; $i < 256; ++$i) {
            self::assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                UserId::generate()->value(),
            );
        }
    }

    #[Test]
    public function itGeneratesUniqueIdsOnEachCall(): void
    {
        self::assertNotSame(UserId::generate()->value(), UserId::generate()->value());
    }

    #[Test]
    public function itCreatesAUserIdFromAValidUuidString(): void
    {
        self::assertSame(self::UUID, UserId::fromString(self::UUID)->value());
    }

    #[Test]
    public function itNormalisesTheUuidToLowercase(): void
    {
        self::assertSame(self::UUID, UserId::fromString(strtoupper(self::UUID))->value());
    }

    #[Test]
    public function itThrowsForANonUuidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('not-a-uuid');
    }

    #[Test]
    public function itThrowsForAnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('');
    }

    #[Test]
    public function itThrowsForAUuidWithTheWrongFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('550e8400-e29b-41d4-a716'); // too short
    }

    #[Test]
    public function itRejectsAUuidWithLeadingCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString('x'.self::UUID); // the pattern must be anchored at the start (^)
    }

    #[Test]
    public function itRejectsAUuidWithTrailingCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UserId::fromString(self::UUID.'x'); // ... and at the end ($)
    }

    #[Test]
    public function itConsidersTwoUserIdsWithTheSameUuidEqual(): void
    {
        self::assertTrue(UserId::fromString(self::UUID)->equals(UserId::fromString(strtoupper(self::UUID))));
    }

    #[Test]
    public function itConsidersTwoUserIdsWithDifferentUuidsUnequal(): void
    {
        self::assertFalse(UserId::fromString(self::UUID)->equals(UserId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8')));
    }

    #[Test]
    public function itCastsToString(): void
    {
        self::assertSame(self::UUID, (string) UserId::fromString(self::UUID));
    }
}
