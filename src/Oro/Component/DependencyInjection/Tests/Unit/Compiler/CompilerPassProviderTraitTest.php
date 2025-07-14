<?php

namespace Oro\Component\DependencyInjection\Tests\Unit\Compiler;

use Oro\Component\DependencyInjection\Tests\Unit\Stub\CompilerPassProviderStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CompilerPassProviderTraitTest extends TestCase
{
    private CompilerPassProviderStub $stub;

    #[\Override]
    protected function setUp(): void
    {
        $this->stub = new CompilerPassProviderStub();
    }

    /**
     * @dataProvider getDataProvider
     */
    public function testFindCompilerPassByClassName(
        array $expectedBeforeOptimizationPasses,
        ?object $expectedResult
    ): void {
        $compilerPassConfig = $this->createMock(PassConfig::class);
        $compilerPassConfig->expects($this->once())
            ->method('getBeforeOptimizationPasses')
            ->willReturn($expectedBeforeOptimizationPasses);

        $container = $this->createMock(ContainerBuilder::class);
        $container->expects($this->once())
            ->method('getCompilerPassConfig')
            ->willReturn($compilerPassConfig);

        $this->assertEquals($expectedResult, $this->stub->getStdClassCompilerPass($container));
    }

    public function getDataProvider(): array
    {
        $object = new \stdClass();

        return [
            'not found' => [
                'expectedBeforeOptimizationPasses' => [],
                'expectedResult' => null
            ],
            'found' => [
                'expectedBeforeOptimizationPasses' => [$object],
                'expectedResult' => $object
            ]
        ];
    }
}
