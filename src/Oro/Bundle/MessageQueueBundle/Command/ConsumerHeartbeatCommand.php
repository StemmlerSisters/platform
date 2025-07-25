<?php

declare(strict_types=1);

namespace Oro\Bundle\MessageQueueBundle\Command;

use Oro\Bundle\CronBundle\Command\CronCommandScheduleDefinitionInterface;
use Oro\Bundle\CronBundle\Command\SynchronousCommandInterface;
use Oro\Bundle\MessageQueueBundle\Consumption\ConsumerHeartbeat;
use Oro\Bundle\SyncBundle\Client\ConnectionChecker;
use Oro\Bundle\SyncBundle\Client\WebsocketClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Pushes a websocket notification if there are no available MQ consumers.
 */
#[AsCommand(
    name: 'oro:cron:message-queue:consumer_heartbeat_check',
    description: 'Pushes a websocket notification if there are no available MQ consumers.'
)]
class ConsumerHeartbeatCommand extends Command implements
    CronCommandScheduleDefinitionInterface,
    SynchronousCommandInterface
{
    private ConsumerHeartbeat $consumerHeartbeat;
    private ConnectionChecker $connectionChecker;
    private WebsocketClientInterface $websocketClient;

    private int $heartBeatUpdatePeriod;

    public function __construct(
        ConsumerHeartbeat $consumerHeartbeat,
        ConnectionChecker $connectionChecker,
        WebsocketClientInterface $websocketClient,
        int $heartBeatUpdatePeriod
    ) {
        $this->consumerHeartbeat = $consumerHeartbeat;
        $this->connectionChecker = $connectionChecker;
        $this->websocketClient = $websocketClient;
        $this->heartBeatUpdatePeriod = $heartBeatUpdatePeriod;
        parent::__construct();
    }

    #[\Override]
    public function getDefaultDefinition(): string
    {
        return \sprintf(
            '*/%u * * * *',
            $this->heartBeatUpdatePeriod
        );
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    public function configure()
    {
        $this
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command checks if there are any active message queue consumers
running, and pushes a websocket notification if there are none. This notification will be shown
as a flash message to the back-office users upon sign in.

  <info>php %command.full_name%</info>

HELP
            );
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpMissingParentCallCommonInspection
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // do nothing if check was disabled with 0 config option value
        if ($this->heartBeatUpdatePeriod === 0) {
            return Command::SUCCESS;
        }

        if (!$this->consumerHeartbeat->isAlive() && $this->connectionChecker->checkConnection()) {
            // Notify frontend that there are no alive consumers.
            $this->websocketClient->publish('oro/message_queue_heartbeat', '');
        }

        return Command::SUCCESS;
    }
}
