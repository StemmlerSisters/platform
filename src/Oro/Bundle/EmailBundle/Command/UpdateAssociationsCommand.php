<?php

declare(strict_types=1);

namespace Oro\Bundle\EmailBundle\Command;

use Oro\Bundle\EmailBundle\Async\Topic\UpdateEmailAssociationsTopic;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Updates email associations.
 */
#[AsCommand(
    name: 'oro:email:update-associations',
    description: 'Updates email associations.'
)]
class UpdateAssociationsCommand extends Command
{
    private MessageProducerInterface $producer;

    public function __construct(MessageProducerInterface $producer)
    {
        parent::__construct();

        $this->producer = $producer;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function configure()
    {
        $this
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command updates email associations.

  <info>php %command.full_name%</info>

HELP
            )
        ;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @noinspection PhpMissingParentCallCommonInspection
     */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->producer->send(UpdateEmailAssociationsTopic::getName(), []);

        $output->writeln('<info>Update of associations has been scheduled.</info>');

        return Command::SUCCESS;
    }
}
