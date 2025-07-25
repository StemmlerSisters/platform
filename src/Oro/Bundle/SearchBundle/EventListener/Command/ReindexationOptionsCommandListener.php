<?php

namespace Oro\Bundle\SearchBundle\EventListener\Command;

use Oro\Bundle\InstallerBundle\Command\PlatformUpdateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Adds two new options to `oro:platform:update` command that allow to skip or postpone
 * the full re-indexation of search index during application update process.
 */
class ReindexationOptionsCommandListener
{
    public const SKIP_REINDEXATION_OPTION_NAME     = 'skip-search-reindexation';
    public const SCHEDULE_REINDEXATION_OPTION_NAME = 'schedule-search-reindexation';

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if ($command instanceof HelpCommand) {
            if ($this->isHelpForPlatformUpdateCommand($command, $event->getInput())) {
                $this->addReindexationOptions(
                    $command->getApplication()->find('oro:platform:update')
                );
            }
        } elseif ($command instanceof PlatformUpdateCommand) {
            $this->addReindexationOptions($command);
        }
    }

    private function isHelpForPlatformUpdateCommand(Command $helpCommand, InputInterface $input): bool
    {
        $innerCommand = null;

        $innerCommandName = $input->getArgument('command_name');
        if ($innerCommandName && $helpCommand->getApplication()->has($innerCommandName)) {
            $innerCommand = $helpCommand->getApplication()->find($innerCommandName);
        }

        if (null !== $innerCommand) {
            return $innerCommand instanceof PlatformUpdateCommand;
        }

        return false !== $input->getParameterOption('oro:platform:update');
    }

    private function addReindexationOptions(Command $command): void
    {
        $this->addOption($command, new InputOption(
            self::SKIP_REINDEXATION_OPTION_NAME,
            null,
            InputOption::VALUE_NONE,
            'Determines whether search data reindexation need to be triggered or not'
        ));
        $this->addOption($command, new InputOption(
            self::SCHEDULE_REINDEXATION_OPTION_NAME,
            null,
            InputOption::VALUE_NONE,
            'Determines whether search data reindexation need to be scheduled or not'
        ));
    }

    private function addOption(Command $command, InputOption $option): void
    {
        $command->getApplication()->getDefinition()->addOption($option);
        $command->getDefinition()->addOption($option);
    }
}
