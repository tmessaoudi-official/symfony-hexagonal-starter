<?php

declare(strict_types=1);

namespace App\Application\DTO\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Domain\Model\Email;
use App\Domain\Model\UserName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * CreateUserInput — validated HTTP input DTO.
 *
 * WHY validation here AND in the Domain?
 * - The Domain value objects (Email, UserName) own the rules: they hold even when the DTO is
 *   bypassed (a CLI command, an import job).
 * - This DTO is the gate at the HTTP boundary: it turns a broken rule into a 422 with a
 *   violation on the right field, instead of a 500 from deep inside the handler.
 *
 * WHY the DTO asks the value objects instead of restating their rules with Assert\Email and
 * Assert\Length: a second copy drifts. Measured on this code: Assert\Email in html5 mode accepted
 * "a..b@example.com" and a 65-character local part, strict mode accepted "é@example.com", and
 * Assert\Length counts characters where UserName counts bytes after trim(). Each of those passed
 * validation and failed in the Domain as a 500 (tests/Functional/Api/UserApiTest.php).
 */
final readonly class CreateUserInput
{
    public function __construct(
        // ApiProperty schemas are documentation only (OpenAPI hints for clients and SDK
        // generators); they enforce nothing. The rule itself lives in the Domain.
        // pcov records no coverage for attribute arguments, so pest --mutate would count every
        // mutant on these two lines as uncovered: they are excluded, and pinned instead by
        // CreateUserInputTest (reflection) and UserApiTest (the exported OpenAPI document).
        #[Assert\NotBlank(message: 'Email is required.', normalizer: 'trim')]
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'email', 'maxLength' => 254])] // @pest-mutate-ignore
        public string $email,

        #[Assert\NotBlank(message: 'Name is required.', normalizer: 'trim')]
        #[ApiProperty(schema: ['type' => 'string', 'minLength' => 2, 'maxLength' => 100])] // @pest-mutate-ignore
        public string $name,
    ) {
    }

    #[Assert\Callback]
    public function validateAgainstTheDomain(ExecutionContextInterface $context): void
    {
        $rules = [
            'email' => Email::fromString(...),
            'name' => UserName::fromString(...),
        ];

        foreach ($rules as $field => $rule) {
            $value = $this->{$field};
            if (trim($value) === '') {
                continue; // NotBlank already reported it.
            }

            try {
                $rule($value);
            } catch (\InvalidArgumentException $e) {
                $context->buildViolation($e->getMessage())->atPath($field)->addViolation();
            }
        }
    }
}
