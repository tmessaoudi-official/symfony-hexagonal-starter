<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use FriendsOfBehat\SymfonyExtension\Context\Environment\InitializedSymfonyExtensionEnvironment;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Generic HTTP/JSON steps, written for this project instead of an external step library
 * (Behatch is abandoned and does not support Symfony 8). Requests go through KernelBrowser.
 *
 * Steps fail by throwing: PHPUnit's Assert cannot be used here, because PHPUnit 13 asserts
 * that its own runner is configured and crashes when an assertion fails outside it.
 */
final class ApiContext implements Context
{
    /** @var array<string, string> */
    private array $headers = [];

    private ?UserContext $userContext = null;

    public function __construct(private readonly KernelBrowser $client)
    {
    }

    #[BeforeScenario]
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $environment = $scope->getEnvironment();
        // FriendsOfBehat's SymfonyExtension uses its own environment class, not Behat's.
        $this->userContext = $environment instanceof InitializedSymfonyExtensionEnvironment
            ? $environment->getContext(UserContext::class)
            : null;
        $this->headers = [];
    }

    #[Given('I set header :name with value :value')]
    public function iSetHeader(string $name, string $value): void
    {
        $this->headers['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        if ('content-type' === strtolower($name)) {
            $this->headers['CONTENT_TYPE'] = $value;
        }
    }

    #[When('I send a :method request to :url')]
    public function iSendARequestTo(string $method, string $url): void
    {
        $this->client->request($method, $this->resolve($url), server: $this->headers);
    }

    #[When('I send a :method request to :url with body:')]
    public function iSendARequestWithBody(string $method, string $url, PyStringNode $body): void
    {
        $this->client->request($method, $this->resolve($url), server: $this->headers, content: $body->getRaw());
    }

    #[Then('the response status code should be :code')]
    public function theResponseStatusCodeShouldBe(int $code): void
    {
        $actual = $this->client->getResponse()->getStatusCode();
        $this->check($code === $actual, sprintf('Expected status %d, got %d.', $code, $actual));
    }

    #[Then('the response should be in JSON')]
    public function theResponseShouldBeInJson(): void
    {
        $this->json();
    }

    #[Then('the JSON node :node should be equal to :expected')]
    public function theJsonNodeShouldBeEqualTo(string $node, string $expected): void
    {
        $value = $this->node($node);
        $actual = is_bool($value) ? ($value ? 'true' : 'false') : (string) (is_scalar($value) ? $value : json_encode($value));
        $this->check($expected === $actual, sprintf('Expected "%s" at node "%s", got "%s".', $expected, $node, $actual));
    }

    #[Then('the JSON node :node should exist')]
    public function theJsonNodeShouldExist(string $node): void
    {
        $this->check(array_key_exists($node, $this->json()), sprintf('JSON node "%s" does not exist.', $node));
    }

    #[Then('the JSON node :node should not be null')]
    public function theJsonNodeShouldNotBeNull(string $node): void
    {
        $this->check(null !== $this->node($node), sprintf('JSON node "%s" is null.', $node));
    }

    #[Then('the JSON node :node should have :count elements')]
    public function theJsonNodeShouldHaveElements(string $node, int $count): void
    {
        $value = $this->node($node);
        $this->check(is_array($value), sprintf('JSON node "%s" is not an array.', $node));
        \assert(is_array($value));
        $this->check(count($value) === $count, sprintf('JSON node "%s" has %d elements, expected %d.', $node, count($value), $count));
    }

    private function resolve(string $url): string
    {
        return $this->userContext?->resolveUrl($url) ?? $url;
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        try {
            $decoded = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('The response is not JSON: '.$e->getMessage()."\n".$content, 0, $e);
        }
        $this->check(is_array($decoded), 'The JSON response is not an object.');
        \assert(is_array($decoded));

        return $decoded;
    }

    private function node(string $node): mixed
    {
        $json = $this->json();
        $this->check(array_key_exists($node, $json), sprintf('JSON node "%s" does not exist.', $node));

        return $json[$node];
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message."\nResponse body: ".substr((string) $this->client->getResponse()->getContent(), 0, 2000));
        }
    }
}
