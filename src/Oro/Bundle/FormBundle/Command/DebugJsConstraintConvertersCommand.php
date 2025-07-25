<?php

declare(strict_types=1);

namespace Oro\Bundle\FormBundle\Command;

use Oro\Bundle\FormBundle\Form\Extension\JsValidation\ConstraintConverterInterface;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * The command aims to get the list of registered JS constraint converters
 */
#[AsCommand(
    name: 'oro:debug:form:js-constraint-converters',
    description: 'Returns the list of registered JS constraint converters in order to priority'
)]
class DebugJsConstraintConvertersCommand extends Command
{
    /** @var iterable|ConstraintConverterInterface[] */
    private iterable $converters;

    public function __construct(iterable $processors)
    {
        parent::__construct();
        $this->converters = $processors;
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders(['Order','Converter']);
        foreach ($this->converters as $order => $converter) {
            $table->addRow([$order, $this->getRealClass($converter)]);
        }
        $table->render();

        return Command::SUCCESS;
    }

    private function getRealClass(ConstraintConverterInterface $converter): string
    {
        if ($converter instanceof LazyLoadingInterface || $converter instanceof LazyObjectInterface) {
            return get_parent_class($converter);
        }

        return \get_class($converter);
    }
}
