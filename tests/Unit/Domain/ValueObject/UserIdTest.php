<?php

declare(strict_types=1);

use App\Domain\Model\UserId;

describe('UserId Value Object', function (): void {
    it('generates a valid UUID v4', function (): void {
        $id = UserId::generate();

        expect($id->value())->toMatch(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
        );
    });

    it('generates unique IDs on each call', function (): void {
        $a = UserId::generate();
        $b = UserId::generate();

        expect($a->value())->not->toBe($b->value());
    });

    it('creates a UserId from a valid UUID string', function (): void {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id   = UserId::fromString($uuid);

        expect($id->value())->toBe(strtolower($uuid));
    });

    it('normalises UUID to lowercase', function (): void {
        $uuid = '550E8400-E29B-41D4-A716-446655440000';
        $id   = UserId::fromString($uuid);

        expect($id->value())->toBe(strtolower($uuid));
    });

    it('throws InvalidArgumentException for a non-UUID string', function (): void {
        UserId::fromString('not-a-uuid');
    })->throws(InvalidArgumentException::class);

    it('throws InvalidArgumentException for an empty string', function (): void {
        UserId::fromString('');
    })->throws(InvalidArgumentException::class);

    it('throws InvalidArgumentException for a UUID with wrong format', function (): void {
        UserId::fromString('550e8400-e29b-41d4-a716'); // too short
    })->throws(InvalidArgumentException::class);

    it('considers two UserIds with the same UUID as equal', function (): void {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $a    = UserId::fromString($uuid);
        $b    = UserId::fromString(strtoupper($uuid));

        expect($a->equals($b))->toBeTrue();
    });

    it('considers two UserIds with different UUIDs as unequal', function (): void {
        $a = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $b = UserId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');

        expect($a->equals($b))->toBeFalse();
    });

    it('casts to string', function (): void {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id   = UserId::fromString($uuid);

        expect((string) $id)->toBe($uuid);
    });
});
