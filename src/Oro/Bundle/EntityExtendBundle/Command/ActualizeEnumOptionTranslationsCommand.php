<?php

declare(strict_types=1);

namespace Oro\Bundle\EntityExtendBundle\Command;

use Oro\Bundle\EntityExtendBundle\Manager\EnumOptionTranslationManager;
use Oro\Bundle\TranslationBundle\Provider\LanguageProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Actualizing oro_enum_option_trans table data based on oro_translation data.
 */
#[AsCommand(
    name: 'oro:entity-extend:actualize:enum-option-translations',
    description: 'Actualizing oro_enum_option_trans table data based on oro_translation data.'
)]
class ActualizeEnumOptionTranslationsCommand extends Command
{
    public function __construct(
        private EnumOptionTranslationManager $enumOptionTranslationManager,
        private LanguageProvider $languageProvider
    ) {
        parent::__construct();
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    public function configure(): void
    {
        $this
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Locale');
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    #[\Override]
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $locale = $input->getOption('locale');

        if ($locale) {
            $locales = [$locale];
        } else {
            $locales = array_map(fn ($language) => $language->getCode(), $this->languageProvider->getLanguages(true));
        }

        foreach ($locales as $locale) {
            $this->enumOptionTranslationManager->actualizeAllForLocale(
                $locale,
            );
            $output->writeln(sprintf('<info>Enum Option Trnanslations actualized for "%s" locale</info>', $locale));
        }

        return Command::SUCCESS;
    }
}
