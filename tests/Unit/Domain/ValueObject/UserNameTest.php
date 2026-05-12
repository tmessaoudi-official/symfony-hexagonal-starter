<?php

declare(strict_types=1);

use App\Domain\Model\UserName;

describe('UserName Value Object', function (): void {
    it('creates a valid name', function (): void {
        $name = UserName::fromString('John Doe');
        expect($name->value())->toBe('John Doe');
    });

    it('accepts a name with international characters', function (): void {
        $name = UserName::fromString('María García');
        expect($name->value())->toBe('María García');
    });

    it('accepts a hyphenated name', function (): void {
        $name = UserName::fromString('Jean-Pierre');
        expect($name->value())->toBe('Jean-Pierre');
    });

    it('accepts the minimum valid name of exactly 2 characters', function (): void {
        $name = UserName::fromString('AB');
        expect($name->value())->toBe('AB');
    });

    it('accepts a name of exactly 100 characters', function (): void {
        $hundredChars = str_repeat('a', 100);
        $name         = UserName::fromString($hundredChars);
        expect($name->value())->toBe($hundredChars);
    });

    it('trims leading and trailing whitespace', function (): void {
        $name = UserName::fromString('  Alice  ');
        expect($name->value())->toBe('Alice');
    });

    it('throws on an empty string', function (): void {
        UserName::fromString('');
    })->throws(InvalidArgumentException::class, 'at least 2 characters');

    it('throws when the name is too short (1 char)', function (): void {
        UserName::fromString('A');
    })->throws(InvalidArgumentException::class, 'at least 2 characters');

    it('throws when the name is whitespace only', function (): void {
        UserName::fromString('   ');
    })->throws(InvalidArgumentException::class, 'at least 2 characters');

    it('throws when the name exceeds 100 characters', function (): void {
        UserName::fromString(str_repeat('a', 101));
    })->throws(InvalidArgumentException::class, 'cannot exceed 100 characters');

    it('considers two UserNames with the same value as equal', function (): void {
        $a = UserName::fromString('Alice');
        $b = UserName::fromString('Alice');
        expect($a->equals($b))->toBeTrue();
    });

    it('considers two UserNames with different values as unequal', function (): void {
        $a = UserName::fromString('Alice');
        $b = UserName::fromString('Bob');
        expect($a->equals($b))->toBeFalse();
    });

    it('casts to string', function (): void {
        $name = UserName::fromString('Alice Smith');
        expect((string) $name)->toBe('Alice Smith');
    });
});
