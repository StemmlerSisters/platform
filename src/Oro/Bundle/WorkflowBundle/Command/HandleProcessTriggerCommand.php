<?php

declare(strict_types=1);

namespace Oro\Bundle\WorkflowBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\WorkflowBundle\Entity\ProcessTrigger;
use Oro\Bundle\WorkflowBundle\Model\ProcessData;
use Oro\Bundle\WorkflowBundle\Model\ProcessHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Executes a process trigger.
 */
#[AsCommand(
    name: 'oro:process:handle-trigger',
    description: 'Executes a process trigger.'
)]
class HandleProcessTriggerCommand extends Command
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private ProcessHandler $processHandler
    ) {
        parent::__construct();
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    public function configure()
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Process name')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Trigger ID')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command executes a specified process trigger.

  <info>php %command.full_name% --name=<process-name> --id=<trigger-id></info>

HELP
            )
            ->addUsage('--name=<process-name> --id=<trigger-id>')
        ;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $processName = $input->getOption('name');

        $triggerId = $input->getOption('id');
        if (!filter_var($triggerId, FILTER_VALIDATE_INT)) {
            $output->writeln('<error>No process trigger identifier defined</error>');

            return Command::FAILURE;
        }

        /** @var ProcessTrigger $processTrigger */
        $processTrigger = $this->doctrine->getRepository(ProcessTrigger::class)->find($triggerId);
        if (!$processTrigger) {
            $output->writeln('<error>Process trigger not found</error>');

            return Command::FAILURE;
        }

        $processDefinition = $processTrigger->getDefinition();
        if ($processName !== $processDefinition->getName()) {
            $output->writeln(sprintf('<error>Trigger not found in process definition "%s"</error>', $processName));

            return Command::FAILURE;
        }

        $processData = new ProcessData();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->doctrine->getManager();
        $entityManager->beginTransaction();
        try {
            $start = microtime(true);

            $this->processHandler->handleTrigger($processTrigger, $processData);
            $entityManager->flush();
            $this->processHandler->finishTrigger($processTrigger, $processData);
            $entityManager->commit();

            $output->writeln(
                sprintf(
                    '<info>[%s] Trigger #%d of process "%s" successfully finished in %f s</info>',
                    (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                    $triggerId,
                    $processDefinition->getName(),
                    microtime(true) - $start
                )
            );
        } catch (\Exception $e) {
            $this->processHandler->finishTrigger($processTrigger, $processData);
            $entityManager->rollback();

            $output->writeln(
                sprintf(
                    '<error>[%s] Trigger #%s of process "%s" failed: %s</error>',
                    (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                    $triggerId,
                    $processDefinition->getName(),
                    $e->getMessage()
                )
            );

            throw $e;
        }

        return Command::SUCCESS;
    }
}
