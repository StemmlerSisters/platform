<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Filter;

use Oro\Bundle\ApiBundle\Filter\FilterNames;
use Oro\Bundle\ApiBundle\Filter\FilterNamesRegistry;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use Oro\Component\Testing\Unit\TestContainerBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FilterNamesRegistryTest extends TestCase
{
    private FilterNames&MockObject $defaultProvider;
    private FilterNames&MockObject $firstProvider;
    private FilterNames&MockObject $secondProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->defaultProvider = $this->createMock(FilterNames::class);
        $this->firstProvider = $this->createMock(FilterNames::class);
        $this->secondProvider = $this->createMock(FilterNames::class);
    }

    private function getRegistry(array $providers): FilterNamesRegistry
    {
        return new FilterNamesRegistry(
            $providers,
            TestContainerBuilder::create()
                ->add('default_provider', $this->defaultProvider)
                ->add('first_provider', $this->firstProvider)
                ->add('second_provider', $this->secondProvider)
                ->getContainer($this),
            new RequestExpressionMatcher()
        );
    }

    public function testGetFilterNamesForUnsupportedRequestType(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot find a filter names provider for the request "rest,another".');

        $requestType = new RequestType(['rest', 'another']);
        $registry = $this->getRegistry([]);
        $registry->getFilterNames($requestType);
    }

    public function testGetFilterNamesShouldReturnDefaultProviderForNotFirstAndSecondRequestType(): void
    {
        $registry = $this->getRegistry(
            [
                ['first_provider', 'first&rest'],
                ['second_provider', 'second&rest'],
                ['default_provider', 'rest']
            ]
        );

        $requestType = new RequestType(['rest']);
        self::assertSame($this->defaultProvider, $registry->getFilterNames($requestType));
    }

    public function testGetFilterNamesShouldReturnFirstProviderForFirstRequestType(): void
    {
        $registry = $this->getRegistry(
            [
                ['first_provider', 'first&rest'],
                ['second_provider', 'second&rest'],
                ['default_provider', 'rest']
            ]
        );

        $requestType = new RequestType(['rest', 'first']);
        self::assertSame($this->firstProvider, $registry->getFilterNames($requestType));
    }

    public function testGetFilterNamesShouldReturnSecondProviderForSecondRequestType(): void
    {
        $registry = $this->getRegistry(
            [
                ['first_provider', 'first&rest'],
                ['second_provider', 'second&rest'],
                ['default_provider', 'rest']
            ]
        );

        $requestType = new RequestType(['rest', 'second', 'another']);
        self::assertSame($this->secondProvider, $registry->getFilterNames($requestType));
    }

    public function testGetFilterNamesShouldReturnDefaultBagIfSpecificBagNotFound(): void
    {
        $registry = $this->getRegistry(
            [
                ['first_provider', 'first'],
                ['default_provider', '']
            ]
        );

        $requestType = new RequestType(['rest', 'another']);
        self::assertSame($this->defaultProvider, $registry->getFilterNames($requestType));
    }
}
