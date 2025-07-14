<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\Engine;

use Oro\Bundle\SearchBundle\Engine\EngineInterface;
use Oro\Bundle\SearchBundle\Engine\EngineParameters;
use Oro\Bundle\SearchBundle\Engine\SearchEngineFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class SearchEngineFactoryTest extends TestCase
{
    private EngineParameters&MockObject $engineParametersBag;
    private ServiceLocator&MockObject $locator;

    #[\Override]
    protected function setUp(): void
    {
        $this->engineParametersBag = $this->createMock(EngineParameters::class);
        $this->locator = $this->createMock(ServiceLocator::class);

        $this->engineParametersBag->expects($this->any())
            ->method('getEngineName')
            ->willReturn('search_engine_name');
    }

    public function testSearchEngineInstanceReturned(): void
    {
        $searchEngineMock = $this->createMock(EngineInterface::class);
        $this->locator->expects(self::once())
            ->method('get')
            ->with($this->engineParametersBag->getEngineName())
            ->willReturn($searchEngineMock);

        self::assertEquals(
            $searchEngineMock,
            SearchEngineFactory::create($this->locator, $this->engineParametersBag)
        );
    }

    /**
     * @dataProvider wrongEngineInstancesProvider
     */
    public function testWrongSearchEngineInstanceTypeReturned(mixed $engine): void
    {
        $this->locator->expects(self::once())
            ->method('get')
            ->with($this->engineParametersBag->getEngineName())
            ->willReturn($engine);

        $this->expectException(UnexpectedTypeException::class);

        SearchEngineFactory::create($this->locator, $this->engineParametersBag);
    }

    public function wrongEngineInstancesProvider(): array
    {
        return [
            'scalar' => ['test string'],
            'array' => [[]],
            'object' => [new \stdClass()]
        ];
    }
}
