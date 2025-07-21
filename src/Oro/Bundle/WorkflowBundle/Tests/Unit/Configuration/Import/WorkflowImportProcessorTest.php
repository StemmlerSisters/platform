<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Configuration\Import;

use Oro\Bundle\WorkflowBundle\Configuration\ConfigImportProcessorInterface;
use Oro\Bundle\WorkflowBundle\Configuration\Import\WorkflowImportProcessor;
use Oro\Bundle\WorkflowBundle\Configuration\Reader\ConfigFileReaderInterface;
use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfigFinderBuilder;
use Oro\Bundle\WorkflowBundle\Exception\WorkflowConfigurationImportException;
use Oro\Bundle\WorkflowBundle\Tests\Unit\Configuration\Import\Stub\StubWorkflowImportCallbackProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class WorkflowImportProcessorTest extends TestCase
{
    private WorkflowConfigFinderBuilder&MockObject $finderBuilder;
    private ConfigFileReaderInterface&MockObject $reader;
    private WorkflowImportProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->reader = $this->createMock(ConfigFileReaderInterface::class);
        $this->finderBuilder = $this->createMock(WorkflowConfigFinderBuilder::class);

        $this->processor = new WorkflowImportProcessor($this->reader, $this->finderBuilder);
    }

    private function configureProcessor(string $resource, string $target, array $replacements = [])
    {
        $this->processor->setResource($resource);
        $this->processor->setTarget($target);
        $this->processor->setReplacements($replacements);
    }

    public function testParentChangesAccepted(): void
    {
        $content = [
            'workflows' => [
                'one' => [
                    'steps' => [
                        'step_a' => null,
                        'step_b' => [
                            'is_start' => true
                        ]
                    ]
                ],
                'two' => [
                    'steps' => [
                        'step_c' => [
                            'is_start' => true
                        ]
                    ]
                ]
            ]
        ];

        $changedByParent = [
            'workflows' => [
                'one' => [
                    'steps' => ['step_c' => ['is_start' => true], 'step_z' => null]
                ],
                'two' => [
                    'steps' => [
                        'step_c' => null, // this would be replaced by target's one node content
                        'step_z' => null
                    ]
                ]
            ]
        ];

        $expectedResult = [
            'workflows' => [
                'one' => [
                    'steps' => [
                        'step_c' => ['is_start' => true],
                        'step_z' => null
                    ]
                ],
                'two' => [
                    'steps' => [
                        'step_c' => ['is_start' => true],
                        'step_z' => null,
                    ]
                ]
            ]
        ];

        $file = new \SplFileInfo(__FILE__);

        $this->configureProcessor('one', 'two', ['steps.step_b']);

        $parent = $this->createMock(ConfigImportProcessorInterface::class);
        $this->processor->setParent($parent);

        $parent->expects($this->exactly(2))
            ->method('process')
            ->with($content, $file)
            ->willReturn($changedByParent);

        $finderMock = $this->createMock(Finder::class);
        $this->finderBuilder->expects($this->once())
            ->method('create')
            ->willReturn($finderMock);

        $finderMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$file]));

        $this->reader->expects($this->once())
            ->method('read')
            ->with($file)
            ->willReturn($content);

        $resultContent = $this->processor->process($content, $file);

        $this->assertEquals(
            $expectedResult,
            $resultContent
        );
    }

    public function testProcessOuterSearch(): void
    {
        $this->configureProcessor('workflow_to_import', 'one', ['steps']);

        $currentContext = [
            'workflows' => [
                'one' => [
                    'steps' => [
                        'step_one' => ['is_start' => true]
                    ]
                ]
            ]
        ];

        $file1Content = ['workflows' => ['not_ours' => ['...']]];

        $file2Content = [
            'workflows' => [
                'not_to_import' => ['entity' => 'Entity2'],
                'workflow_to_import' => ['entity' => 'Entity1', 'steps' => ['will be replaced']],
            ]
        ];

        $result = [
            'workflows' => [
                'one' => [
                    'entity' => 'Entity1',
                    'steps' => ['step_one' => ['is_start' => true]]
                ]
            ]
        ];

        $parent = $this->createMock(ConfigImportProcessorInterface::class);

        $finderMock = $this->createMock(Finder::class);

        $currentFile = new \SplFileInfo(__FILE__);
        $file1 = new \SplFileInfo('file1');
        $file2 = new \SplFileInfo('file2');

        $parent->expects($this->exactly(3))
            ->method('process')
            ->withConsecutive([$currentContext, $currentFile], [$file1Content, $file1], [$file2Content, $file2])
            ->willReturnOnConsecutiveCalls($currentContext, $file1Content, $file2Content);

        $this->finderBuilder->expects($this->once())
            ->method('create')
            ->willReturn($finderMock);

        $finderMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$file1, $file2]));

        $this->reader->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive([$file1], [$file2])
            ->willReturnOnConsecutiveCalls($file1Content, $file2Content);

        $this->processor->setParent($parent);
        $processed = $this->processor->process($currentContext, $currentFile);

        $this->assertEquals($result, $processed);
    }

    public function testImplementsWorkflowImportTrait(): void
    {
        $accessors = [
            [
                'resource',
                'name of resource'
            ],
            [
                'target',
                'name of target'
            ],
            [
                'replacements',
                ['array', 'of', 'replacements']
            ]
        ];

        foreach ($accessors as [$name, $value]) {
            $setter = 'set' . ucfirst($name);
            $this->processor->{$setter}($value);
            $getter = 'get' . ucfirst($name);
            $this->assertSame($value, $this->processor->{$getter}());
        }
    }

    /**
     * @dataProvider propertiesToConfigure
     */
    public function testMustBeConfiguredBeforeUsage(string $property, string $type): void
    {
        $getter = 'get' . ucfirst($property);

        $this->expectException(\TypeError::class);

        if (PHP_VERSION_ID < 80000) {
            $messagePattern = 'Return value of %s::%s() must be of the type %s, null returned';
        } else {
            $messagePattern = '%s::%s(): Return value must be of type %s, null returned';
        }
        $this->expectExceptionMessage(sprintf(
            $messagePattern,
            WorkflowImportProcessor::class,
            $getter,
            $type
        ));

        $this->processor->{$getter}();
    }

    public function propertiesToConfigure(): array
    {
        return [
            ['target', 'string'],
            ['resource', 'string'],
            ['replacements', 'array']
        ];
    }

    public function testExceptionWorkflowForImportNotFound(): void
    {
        $this->configureProcessor('workflow_to_import', 'one', ['steps']);

        $currentContext = [
            'workflows' => [
                'one' => [
                    'steps' => [
                        'step_one' => ['is_start' => true]
                    ]
                ]
            ]
        ];

        $file1Content = ['workflows' => ['not_ours' => ['...']]];

        $file2Content = [
            'workflows' => [
                'not_to_import' => ['entity' => 'Entity2'],
                'not_to_import_also' => ['entity' => 'Entity1', 'steps' => ['will be replaced']],
            ]
        ];

        $finderMock = $this->createMock(Finder::class);

        $currentFile = new \SplFileInfo(__FILE__);
        $file1 = new \SplFileInfo('file1');
        $file2 = new \SplFileInfo('file2');

        $this->finderBuilder->expects($this->once())
            ->method('create')
            ->willReturn($finderMock);

        $finderMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$file1, $file2]));

        $this->reader->expects($this->exactly(2))
            ->method('read')
            ->withConsecutive([$file1], [$file2])
            ->willReturnOnConsecutiveCalls($file1Content, $file2Content);

        $this->expectException(WorkflowConfigurationImportException::class);
        $this->expectExceptionMessage('Can not find workflow `workflow_to_import` for import.');

        $this->processor->process($currentContext, $currentFile);
    }

    public function testInProgress(): void
    {
        $stubCbParentProcessor = new StubWorkflowImportCallbackProcessor(function (array $content) {
            $this->assertTrue($this->processor->inProgress());

            return $content;
        });

        $this->processor->setParent($stubCbParentProcessor);

        $content = [
            'workflows' => [
                'workflow_to_import' => ['*' => [42]],
                'one' => ['*' => ['everything']]
            ]
        ];

        $result = [
            'workflows' => [
                'workflow_to_import' => ['*' => [42]],
                'one' => ['*' => ['everything', 42]]
            ]
        ];

        $file1 = new \SplFileInfo('file1');
        $finderMock = $this->createMock(Finder::class);
        $this->finderBuilder->expects($this->once())
            ->method('create')
            ->willReturn($finderMock);

        $finderMock->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator([$file1]));

        $this->reader->expects($this->once())
            ->method('read')
            ->with($file1)
            ->willReturn($content);

        $this->configureProcessor('workflow_to_import', 'one', ['steps']);
        $processed = $this->processor->process($content, new \SplFileInfo(__FILE__));
        $this->assertEquals($result, $processed);
    }
}
