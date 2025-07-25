<?php

declare(strict_types=1);

namespace Oro\Bundle\TranslationBundle\Command;

use Oro\Bundle\TranslationBundle\Cache\RebuildTranslationCacheProcessor;
use Oro\Bundle\TranslationBundle\Translation\TranslationMessageSanitizationErrorCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rebuilds the translation cache.
 */
#[AsCommand(
    name: 'oro:translation:rebuild-cache',
    description: 'Rebuilds the translation cache.'
)]
class OroTranslationRebuildCacheCommand extends Command
{
    private RebuildTranslationCacheProcessor $rebuildTranslationCacheProcessor;
    private TranslationMessageSanitizationErrorCollection $sanitizationErrorCollection;

    public function __construct(
        RebuildTranslationCacheProcessor $rebuildTranslationCacheProcessor,
        TranslationMessageSanitizationErrorCollection $sanitizationErrorCollection
    ) {
        parent::__construct();
        $this->rebuildTranslationCacheProcessor = $rebuildTranslationCacheProcessor;
        $this->sanitizationErrorCollection = $sanitizationErrorCollection;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    public function configure()
    {
        $this
            ->addOption(
                'show-sanitization-errors',
                null,
                InputOption::VALUE_NONE,
                'Show information about sanitization errors'
            )
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command rebuilds the translation cache.

  <info>php %command.full_name%</info>

HELP
            );
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->text('Rebuilding the translation cache ...');

        if (!$this->rebuildTranslationCacheProcessor->rebuildCache()) {
            $io->error('The rebuild failed.');

            return Command::FAILURE;
        }

        $io->success('The rebuild complete.');

        if ($input->getOption('show-sanitization-errors')) {
            $this->renderSanitizationErrors($io);
        }

        return Command::SUCCESS;
    }

    private function renderSanitizationErrors(SymfonyStyle $io): void
    {
        $errors = $this->sanitizationErrorCollection->all();
        if (!$errors) {
            return;
        }

        $io->text('Unsafe messages');
        $rows = [];
        foreach ($errors as $error) {
            $rows[] = [
                $error->getLocale(),
                $error->getDomain(),
                $error->getMessageKey(),
                $error->getOriginalMessage(),
                $error->getSanitizedMessage()
            ];
        }
        $io->table(
            ['Locale', 'Domain', 'Message Key', 'Original Message', 'Sanitized Message'],
            $rows
        );
    }
}
