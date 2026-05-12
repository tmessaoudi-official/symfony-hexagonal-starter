<?php

declare(strict_types=1);

namespace App\Application\DTO\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * CreateUserInput — validated HTTP input DTO.
 *
 * WHY validation constraints here and not in the Domain?
 * - Symfony Validator is an Infrastructure concern — the Domain cannot depend on it.
 * - This DTO is the first validation gate at the system boundary (the HTTP adapter).
 * - Domain validation (Email VO, UserName VO) is the second, inner gate.
 * - Having both is intentional: the DTO gives human-readable error messages back to the
 *   caller; the domain invariants ensure correctness even when the DTO is bypassed
 *   (e.g. a CLI command or an import job).
 */
final readonly class CreateUserInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: '"{{ value }}" is not a valid email address.')]
        #[Assert\Length(max: 254, maxMessage: 'Email cannot exceed 254 characters.')]
        public string $email,

        #[Assert\NotBlank(message: 'Name is required.')]
        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'Name must be at least {{ limit }} characters.',
            maxMessage: 'Name cannot exceed {{ limit }} characters.',
        )]
        public string $name,
    ) {}
}
