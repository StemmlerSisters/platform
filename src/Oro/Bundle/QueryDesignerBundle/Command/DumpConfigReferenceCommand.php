<?php

declare(strict_types=1);

namespace Oro\Bundle\QueryDesignerBundle\Command;

use Oro\Bundle\QueryDesignerBundle\QueryDesigner\Configuration;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dumps the reference structure for Resources/config/oro/query_designer.yml.
 */
#[AsCommand(
    name: 'oro:query-designer:config:dump-reference',
    description: 'Dumps the reference structure for Resources/config/oro/query_designer.yml.'
)]
class DumpConfigReferenceCommand extends Command
{
    private Configuration $configuration;

    public function __construct(Configuration $configuration)
    {
        parent::__construct();
        $this->configuration = $configuration;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function configure()
    {
        $this
            ->setHelp(
                <<<'HELP'
The <info>%command.name%</info> command dumps the reference structure
for <comment>Resources/config/oro/query_designer.yml</comment> files.

  <info>php %command.full_name%</info>

HELP
            )
        ;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $output->writeln('# The structure of "Resources/config/oro/query_designer.yml"');
        $dumper = new YamlReferenceDumper();
        $output->writeln($dumper->dump($this->configuration));

        return Command::SUCCESS;
    }
}
