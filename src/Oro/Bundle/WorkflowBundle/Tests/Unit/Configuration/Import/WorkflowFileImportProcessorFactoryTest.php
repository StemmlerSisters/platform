<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Configuration\Import;

use Oro\Bundle\WorkflowBundle\Configuration\Import\WorkflowFileImportProcessor;
use Oro\Bundle\WorkflowBundle\Configuration\Import\WorkflowFileImportProcessorFactory;
use Oro\Bundle\WorkflowBundle\Configuration\Reader\ConfigFileReaderInterface;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocatorInterface;

class WorkflowFileImportProcessorFactoryTest extends TestCase
{
    private WorkflowFileImportProcessorFactory $factory;
    private ConfigFileReaderInterface&MockObject $reader;

    #[\Override]
    protected function setUp(): void
    {
        $this->reader = $this->createMock(ConfigFileReaderInterface::class);
        $fileLocator = $this->createMock(FileLocatorInterface::class);
        $this->factory = new WorkflowFileImportProcessorFactory($this->reader, $fileLocator);
    }

    /**
     * @dataProvider applicableTestCases
     * @param mixed $import
     * @param bool $expected
     */
    public function testApplicable($import, bool $expected): void
    {
        $this->assertEquals($expected, $this->factory->isApplicable($import));
    }

    /**
     * @return \Generator
     */
    public function applicableTestCases()
    {
        yield 'correct' => [
            'import' => [
                'resource' => './file',
                'workflow' => 'name1',
                'as' => 'name2',
                'replace' => ['node']
            ],
            true
        ];

        yield 'incorrect 1' => [
            'import' => [
                'resource' => './file'
            ],
            false
        ];

        yield 'incorrect 2' => [
            'import' => './file1',
            false
        ];

        yield 'incorrect 3' => [
            'import' => [
                'workflow' => 'name',
                'as' => 'name',
                'replace' => ['node']
            ],
            false
        ];
    }

    public function testCreate(): void
    {
        $resource = './file';
        $target = 'target';
        $workflowForImport = 'resource';
        $replace = ['node1', 'node2'];

        $import = [
            'workflow' => $workflowForImport,
            'as' => $target,
            'resource' => $resource,
            'replace' => $replace
        ];

        $instance = $this->factory->create($import);

        $this->assertInstanceOf(WorkflowFileImportProcessor::class, $instance);

        $this->assertSame($resource, ReflectionUtil::getPropertyValue($instance, 'file'));
        $this->assertSame($workflowForImport, ReflectionUtil::getPropertyValue($instance, 'resource'));
        $this->assertSame($target, ReflectionUtil::getPropertyValue($instance, 'target'));
        $this->assertSame($replace, ReflectionUtil::getPropertyValue($instance, 'replacements'));
    }
}
