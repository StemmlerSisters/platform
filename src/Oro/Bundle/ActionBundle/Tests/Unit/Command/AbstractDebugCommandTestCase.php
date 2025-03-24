<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Command;

use Oro\Bundle\ActionBundle\Tests\Unit\Stub\TestEntity1;
use Oro\Component\ConfigExpression\FactoryWithTypesInterface;
use Oro\Component\Testing\Unit\Command\Stub\OutputStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractDebugCommandTestCase extends TestCase
{
    protected FactoryWithTypesInterface&MockObject $factory;
    protected ContainerInterface&MockObject $container;
    protected InputInterface&MockObject $input;
    protected OutputStub $output;
    protected Command $command;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = $this->createMock(FactoryWithTypesInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = new OutputStub();

        $this->command = $this->getCommandInstance($this->container, $this->factory);
    }

    /**
     * @dataProvider executeProvider
     */
    public function testExecute(
        array $types,
        array $expected,
        ?string $argument = null,
        ?\Throwable $exception = null
    ): void {
        $this->input->expects(self::once())
            ->method('getArgument')
            ->willReturn($argument);

        $this->factory->expects(self::any())
            ->method('isTypeExists')
            ->with($argument)
            ->willReturn(isset($types[$argument]));

        $this->factory->expects(self::any())
            ->method('getTypes')
            ->willReturn($types);

        $this->container->expects(self::any())
            ->method('get')
            ->willReturnCallback(function ($serviceId) use ($exception, $types) {
                if ($exception) {
                    throw $exception;
                }

                self::assertContains($serviceId, $types);

                return new TestEntity1();
            });

        $this->command->run($this->input, $this->output);

        $outputContent = implode("\n", $this->output->messages);
        foreach ($expected as $message) {
            self::assertStringContainsString($message, $outputContent);
        }
    }

    public function executeProvider(): array
    {
        return [
            'no types' => [
                'types' => [],
                'expected' => [
                    'Short Description',
                ],
            ],
            'with types' => [
                'types' => [
                    'name1' => 'type1',
                    'name2' => 'type2',
                ],
                'expected' => [
                    'Short Description',
                    'name1',
                    'name2',
                    'This is description',
                    'of the class',
                ],
            ],
            'no types with argument' => [
                'types' => [],
                'expected' => [
                    'Type "name1" is not found',
                ],
                'argument' => 'name1',
            ],
            'with types with argument' => [
                'types' => [
                    'name1' => 'type1',
                    'name2' => 'type2',
                ],
                'expected' => [
                    'Full Description',
                    'name1',
                    'type1',
                    'Class TestEntity1',
                ],
                'argument' => 'name1',
            ],
            'type error exception' => [
                'types' => [
                    'name1' => 'type1',
                    'name2' => 'type2',
                ],
                'expected' => [
                    'Can not load Service "type1": test message1',
                    'Short Description'
                ],
                'argument' => null,
                'exception' => new \TypeError('test message1')
            ],
            'error exception' => [
                'types' => [
                    'name1' => 'type1',
                    'name2' => 'type2',
                ],
                'expected' => [
                    'Can not load Service "type1": test message2',
                    'Short Description'
                ],
                'argument' => null,
                'exception' => new \ErrorException('test message2')
            ],
        ];
    }

    abstract protected function getArgumentName(): string;

    abstract protected function getCommandInstance(
        ContainerInterface $container,
        FactoryWithTypesInterface $factory
    ): Command;
}
