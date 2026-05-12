<?php

declare(strict_types=1);

use App\Domain\Model\Email;

describe('Email Value Object', function (): void {
    it('creates a valid email and normalises to lowercase', function (): void {
        $email = Email::fromString('User@EXAMPLE.COM');
        expect($email->value())->toBe('user@example.com');
    });

    it('extracts the domain part', function (): void {
        $email = Email::fromString('alice@example.org');
        expect($email->domain())->toBe('example.org');
    });

    it('considers two emails with the same value equal', function (): void {
        $a = Email::fromString('alice@example.com');
        $b = Email::fromString('Alice@EXAMPLE.COM');
        expect($a->equals($b))->toBeTrue();
    });

    it('considers two emails with different values unequal', function (): void {
        $a = Email::fromString('alice@example.com');
        $b = Email::fromString('bob@example.com');
        expect($a->equals($b))->toBeFalse();
    });

    it('throws on an empty string', function (): void {
        Email::fromString('');
    })->throws(InvalidArgumentException::class, 'cannot be empty');

    it('throws on a string without @', function (): void {
        Email::fromString('notanemail');
    })->throws(InvalidArgumentException::class, 'not a valid email');

    it('throws on an email exceeding 254 characters', function (): void {
        $local  = str_repeat('a', 243);
        Email::fromString($local . '@example.com'); // 255 chars
    })->throws(InvalidArgumentException::class, 'maximum length');

    it('casts to string', function (): void {
        $email = Email::fromString('alice@example.com');
        expect((string) $email)->toBe('alice@example.com');
    });

    // ─── Additional edge-case coverage ──────────────────────────────────────

    it('accepts plus-tagged email addresses', function (): void {
        $email = Email::fromString('user+tag@domain.co.uk');
        expect($email->value())->toBe('user+tag@domain.co.uk');
    });

    it('accepts dotted local-part with subdomain', function (): void {
        $email = Email::fromString('user.name@sub.domain.org');
        expect($email->value())->toBe('user.name@sub.domain.org');
    });

    it('throws on an email with no domain after @', function (): void {
        Email::fromString('user@');
    })->throws(InvalidArgumentException::class, 'not a valid email');

    it('throws on an email with spaces', function (): void {
        Email::fromString('user @example.com');
    })->throws(InvalidArgumentException::class, 'not a valid email');

    it('throws on an email with double @ sign', function (): void {
        Email::fromString('user@@example.com');
    })->throws(InvalidArgumentException::class, 'not a valid email');
});
