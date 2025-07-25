<?php

declare(strict_types=1);

namespace Oro\Bundle\AttachmentBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\AttachmentBundle\Entity\File;
use Oro\Bundle\AttachmentBundle\Migration\FilteredAttachmentMigrationServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Migrates filtered attachments to the new directory structure.
 */
#[AsCommand(
    name: 'oro:attachment:migrate-directory-structure',
    description: 'Migrates filtered attachments to the new directory structure.'
)]
class MigrateImagesCommand extends Command
{
    private ManagerRegistry $registry;
    private FilteredAttachmentMigrationServiceInterface $migrationService;

    private string $prefix;

    public function __construct(
        ManagerRegistry $registry,
        FilteredAttachmentMigrationServiceInterface $migrationService,
        string $prefix
    ) {
        $this->registry = $registry;
        $this->migrationService = $migrationService;
        $this->prefix = $prefix;

        parent::__construct();
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function configure()
    {
        $this
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command migrates filtered attachments
to the new directory structure to improve performance and support large number
of files in the filesystem.

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
        $output->writeln('Starting attachments migration');
        $manager = $this->registry->getManagerForClass(File::class);
        $this->migrationService->setManager($manager);
        $this->migrationService->migrate($this->prefix, $this->prefix);
        $output->writeln('Attachments migration finished');

        return Command::SUCCESS;
    }
}
