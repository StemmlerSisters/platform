<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\DependencyInjection\Compiler;

use Oro\Bundle\SecurityBundle\DependencyInjection\Compiler\OwnershipDecisionMakerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class OwnershipDecisionMakerPassTest extends TestCase
{
    private OwnershipDecisionMakerPass $compiler;

    #[\Override]
    protected function setUp(): void
    {
        $this->compiler = new OwnershipDecisionMakerPass();
    }

    public function testProcessNotRegisterOwnershipDecisionMaker(): void
    {
        $container = new ContainerBuilder();

        $this->compiler->process($container);
    }

    public function testProcess(): void
    {
        $container = new ContainerBuilder();
        $chainOwnershipDecisionMakerDef = $container->register('oro_security.owner.ownership_decision_maker.chain');

        $container->register('ownership_decision_maker_1')
            ->addTag('oro_security.owner.ownership_decision_maker', ['class' => 'Test\Class1']);
        $container->register('ownership_decision_maker_2')
            ->addTag('oro_security.owner.ownership_decision_maker', ['class' => 'Test\Class2']);

        $this->compiler->process($container);

        self::assertEquals(
            [
                ['addOwnershipDecisionMaker', [new Reference('ownership_decision_maker_1')]],
                ['addOwnershipDecisionMaker', [new Reference('ownership_decision_maker_2')]]
            ],
            $chainOwnershipDecisionMakerDef->getMethodCalls()
        );
    }

    public function testProcessEmptyOwnershipDecisionMakers(): void
    {
        $container = new ContainerBuilder();
        $chainOwnershipDecisionMakerDef = $container->register('oro_security.owner.ownership_decision_maker.chain');

        $this->compiler->process($container);

        self::assertSame([], $chainOwnershipDecisionMakerDef->getMethodCalls());
    }
}
