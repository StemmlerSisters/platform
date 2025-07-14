<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Configuration;

use Oro\Bundle\WorkflowBundle\Configuration\ConfigFinderFactory;
use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfigFinderBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class WorkflowConfigFinderFactoryTest extends TestCase
{
    private ConfigFinderFactory&MockObject $finderFactory;
    private WorkflowConfigFinderBuilder $workflowConfigFinderBuilder;

    #[\Override]
    protected function setUp(): void
    {
        $this->finderFactory = $this->createMock(ConfigFinderFactory::class);

        $this->workflowConfigFinderBuilder = new WorkflowConfigFinderBuilder($this->finderFactory);
    }

    public function testExceptionOnNotConfiguredSubDirectory(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            'Can not create finder. Not properly configured. No subDirectory specified.'
        );

        $this->workflowConfigFinderBuilder->create();
    }

    public function testExceptionOnNotConfiguredConfigFileName(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            'Can not create finder. Not properly configured. No fileName specified.'
        );

        $this->workflowConfigFinderBuilder->setSubDirectory('subdir');

        $this->workflowConfigFinderBuilder->create();
    }

    public function testConfiguredPropertiesPassToFactory(): void
    {
        $finder1 = $this->createMock(Finder::class);
        $finder2 = $this->createMock(Finder::class);

        $this->finderFactory->expects($this->exactly(2))
            ->method('create')
            ->withConsecutive(
                ['subDir1', 'appSubDir1', 'fileName1'],
                ['subDir2', 'appSubDir2', 'fileName2']
            )
            ->willReturnOnConsecutiveCalls($finder1, $finder2);

        $this->workflowConfigFinderBuilder->setSubDirectory('subDir1');
        $this->workflowConfigFinderBuilder->setFileName('fileName1');
        $this->workflowConfigFinderBuilder->setAppSubDirectory('appSubDir1');
        $this->assertSame($finder1, $this->workflowConfigFinderBuilder->create());

        $this->workflowConfigFinderBuilder->setSubDirectory('subDir2');
        $this->workflowConfigFinderBuilder->setFileName('fileName2');
        $this->workflowConfigFinderBuilder->setAppSubDirectory('appSubDir2');

        $this->assertSame($finder2, $this->workflowConfigFinderBuilder->create());
    }
}
