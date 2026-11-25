<?php

declare(strict_types=1);

use ApiPlatform\Metadata\ApiProperty;
use App\Application\DTO\Input\CreateUserInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

uses(TestCase::class);

/**
 * @return list<string> one "path: message" entry per violation, duplicates kept
 */
function violationsOf(CreateUserInput $input): array
{
    $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    $out = [];
    foreach ($validator->validate($input) as $violation) {
        $out[] = $violation->getPropertyPath().': '.$violation->getMessage();
    }

    return $out;
}

describe('CreateUserInput (Unit)', function (): void {
    it('accepts what the Domain accepts', function (): void {
        expect(violationsOf(new CreateUserInput('ada@example.com', 'Ada Lovelace')))->toBe([]);
    });

    it('reports a Domain refusal on the right field, with the Domain message', function (): void {
        expect(violationsOf(new CreateUserInput('a..b@example.com', 'Ada')))
            ->toBe(['email: "a..b@example.com" is not a valid email address.']);

        expect(violationsOf(new CreateUserInput('ada@example.com', '  A  ')))
            ->toBe(['name: UserName must be at least 2 characters long.']);
    });

    it('reports both fields when both are wrong', function (): void {
        expect(violationsOf(new CreateUserInput('a@b.123', str_repeat('é', 51))))
            ->toBe([
                'email: "a@b.123" is not a valid email address.',
                'name: UserName cannot exceed 100 characters.',
            ]);
    });

    it('reports a blank field once, as required, not again as a Domain refusal', function (): void {
        expect(violationsOf(new CreateUserInput('   ', '')))
            ->toBe(['email: Email is required.', 'name: Name is required.']);
    });

    it('publishes the OpenAPI hints of the Domain rules', function (): void {
        $schemaOf = static fn (string $property): ?array => new ReflectionProperty(CreateUserInput::class, $property)
            ->getAttributes(ApiProperty::class)[0]->newInstance()->getSchema();

        expect($schemaOf('email'))->toBe(['type' => 'string', 'format' => 'email', 'maxLength' => 254])
            ->and($schemaOf('name'))->toBe(['type' => 'string', 'minLength' => 2, 'maxLength' => 100]);
    });

    it('still checks the other field when one is blank', function (): void {
        expect(violationsOf(new CreateUserInput('', 'A')))
            // The class-level Callback runs before the property constraints, hence this order.
            ->toBe(['name: UserName must be at least 2 characters long.', 'email: Email is required.']);
    });
});
