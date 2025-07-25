<?php

declare(strict_types=1);

namespace Oro\Bundle\InstallerBundle\Command;

use Oro\Bundle\InstallerBundle\CommandExecutor;
use Oro\Bundle\InstallerBundle\ScriptExecutor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Runs OroScript files in the application scope.
 */
#[AsCommand(
    name: 'oro:platform:run-script',
    description: 'Runs OroScript files in the application scope.'
)]
class RunScriptsCommand extends Command
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        parent::__construct();
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function configure()
    {
        $this
            ->addArgument('script', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Script files')
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command runs OroScript files while providing them with
the container and command runner instance references.

  <info>php %command.full_name% <script1> <script2> <scriptN></info>

HELP
            )
        ;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandExecutor = new CommandExecutor(
            $input,
            $output,
            $this->getApplication(),
            $this->getContainer()->get('oro_cache.oro_data_cache_manager')
        );
        $scriptExecutor = new ScriptExecutor(
            $output,
            $this->getContainer(),
            $commandExecutor
        );
        $scriptFiles = $input->getArgument('script');
        foreach ($scriptFiles as $scriptFile) {
            $scriptExecutor->runScript($scriptFile);
        }

        return Command::SUCCESS;
    }

    private function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
