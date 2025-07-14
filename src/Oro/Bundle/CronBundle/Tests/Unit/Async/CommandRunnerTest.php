<?php

namespace Oro\Bundle\CronBundle\Tests\Unit\Async;

use Oro\Bundle\CronBundle\Async\CommandRunner;
use Oro\Bundle\CronBundle\Async\Topic\RunCommandTopic;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use PHPUnit\Framework\TestCase;

class CommandRunnerTest extends TestCase
{
    public function testShouldBeConstructedWithAllRequiredArguments(): void
    {
        new  CommandRunner($this->createMock(MessageProducerInterface::class));
    }

    public function testShouldSendMessageWithCommandParams(): void
    {
        $testCommandName = 'oro:test';
        $testCommandArguments = ['argument' => 'value'];

        $producer = $this->createMock(MessageProducerInterface::class);
        $producer->expects($this->once())
            ->method('send')
            ->with(
                RunCommandTopic::getName(),
                [
                    'command' => $testCommandName,
                    'arguments' => $testCommandArguments
                ]
            );

        $runner = new CommandRunner($producer);
        $runner->run($testCommandName, $testCommandArguments);
    }
}
