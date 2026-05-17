<?php

declare(strict_types=1);

namespace App\Domain\Service;

/**
 * Represents the outcome of an eligibility check.
 * Carries the reason when ineligible so callers can surface a meaningful message.
 * Zero framework imports — pure PHP value object.
 */
final readonly class EligibilityResult
{
    private function __construct(
        private bool $eligible,
        private ?string $reason,
    ) {}

    public static function eligible(): self
    {
        return new self(true, null);
    }

    public static function ineligible(string $reason): self
    {
        return new self(false, $reason);
    }

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
