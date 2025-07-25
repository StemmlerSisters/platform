<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\EventListener\Command;

use Oro\Bundle\InstallerBundle\CommandExecutor;
use Oro\Bundle\InstallerBundle\InstallerEvent;
use Oro\Bundle\SearchBundle\EventListener\Command\InstallCommandListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class InstallCommandListenerTest extends TestCase
{
    private RequestStack $requestStack;
    private Command&MockObject $command;
    private InputInterface&MockObject $input;
    private OutputInterface&MockObject $output;
    private CommandExecutor&MockObject $commandExecutor;
    private InstallerEvent $event;

    #[\Override]
    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();

        $this->command = $this->createMock(Command::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->commandExecutor = $this->createMock(CommandExecutor::class);

        $this->event = new InstallerEvent($this->command, $this->input, $this->output, $this->commandExecutor);
    }

    public function testOnAfterDatabasePreparationNotSupportedCommand(): void
    {
        $this->command->expects($this->once())
            ->method('getName')
            ->willReturn('some:command');

        $this->input->expects($this->never())
            ->method($this->anything());

        $this->output->expects($this->never())
            ->method($this->anything());

        $this->commandExecutor->expects($this->never())
            ->method($this->anything());

        $listener = new InstallCommandListener($this->requestStack, 'test:command', false);
        $listener->onAfterDatabasePreparation($this->event);
    }

    /**
     * @dataProvider onAfterDatabasePreparationProvider
     */
    public function testOnAfterDatabasePreparation(bool $isScheduled, bool $isIsolated): void
    {
        $commandName = 'test:command';

        $this->command->expects($this->once())
            ->method('getName')
            ->willReturn('oro:install');

        $this->input->expects($this->once())
            ->method('hasOption')
            ->with('timeout')
            ->willReturn(true);
        $this->input->expects($this->once())
            ->method('getOption')
            ->with('timeout')
            ->willReturn(500);

        $this->output->expects($this->exactly(2))
            ->method('writeln')
            ->withConsecutive(
                ['<comment>Running full re-indexation with "test:command" command</comment>'],
                ['']
            );

        $expectedParams = ['--scheduled' => true, '--process-isolation' => true, '--process-timeout' => 500];
        if (!$isScheduled) {
            unset($expectedParams['--scheduled']);
        }
        if (!$isIsolated) {
            $this->requestStack->push(new Request());
            unset($expectedParams['--process-isolation']);
        }

        $this->commandExecutor->expects($this->once())
            ->method('runCommand')
            ->with($commandName, $expectedParams);

        $listener = new InstallCommandListener($this->requestStack, $commandName, $isScheduled);
        $listener->onAfterDatabasePreparation($this->event);
    }

    public function onAfterDatabasePreparationProvider(): array
    {
        return [
            'not scheduled re-indexation' => [
                'isScheduled' => false,
                'isIsolated' => false,
            ],
            'scheduled re-indexation' => [
                'isScheduled' => true,
                'isIsolated' => false,
            ],
            'not scheduled re-indexation, ' => [
                'isScheduled' => false,
                'isIsolated' => true,
            ],
            'scheduled re-indexation, ' => [
                'isScheduled' => true,
                'isIsolated' => true,
            ]
        ];
    }
}
