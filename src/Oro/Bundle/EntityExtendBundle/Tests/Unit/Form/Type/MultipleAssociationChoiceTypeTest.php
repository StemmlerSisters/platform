<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Form\Type;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Form\Type\MultipleAssociationChoiceType;
use Oro\Bundle\EntityExtendBundle\Form\Util\AssociationTypeHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormView;

class MultipleAssociationChoiceTypeTest extends AssociationTypeTestCase
{
    private ConfigProvider&MockObject $entityConfigProvider;

    #[\Override]
    protected function getFormType(): AbstractType
    {
        $config1 = new Config(new EntityConfigId('grouping', 'Test\Entity1'));
        $config2 = new Config(new EntityConfigId('grouping', 'Test\Entity2'));
        $config2->set('groups', []);
        $config3 = new Config(new EntityConfigId('grouping', 'Test\Entity3'));
        $config3->set('groups', ['test']);
        $config4 = new Config(new EntityConfigId('grouping', 'Test\Entity4'));
        $config4->set('groups', ['test', 'test1']);
        $config5 = new Config(new EntityConfigId('grouping', 'Test\Entity5'));
        $config5->set('groups', ['test']);
        $this->groupingConfigProvider = $this->createMock(ConfigProvider::class);
        $this->groupingConfigProvider->expects(self::any())
            ->method('getConfigs')
            ->willReturn([$config1, $config2, $config3, $config4, $config5]);

        $entityConfig3 = new Config(new EntityConfigId('entity', 'Test\Entity3'));
        $entityConfig3->set('plural_label', 'Entity3');
        $entityConfig4 = new Config(new EntityConfigId('entity', 'Test\Entity4'));
        $entityConfig4->set('plural_label', 'Entity4');
        $entityConfig5 = new Config(new EntityConfigId('entity', 'Test\Entity5'));
        $entityConfig5->set('plural_label', 'Entity5');
        $this->entityConfigProvider = $this->createMock(ConfigProvider::class);
        $this->entityConfigProvider->expects(self::any())
            ->method('getConfig')
            ->willReturnMap([
                ['Test\Entity3', null, $entityConfig3],
                ['Test\Entity4', null, $entityConfig4],
                ['Test\Entity5', null, $entityConfig5],
            ]);

        return new MultipleAssociationChoiceType(
            new AssociationTypeHelper($this->configManager),
            $this->configManager
        );
    }

    /**
     * @dataProvider submitProvider
     */
    public function testSubmit(
        array $newVal,
        ?array $oldVal,
        string $state,
        bool $isSetStateExpected,
        ?array $immutable,
        array $expectedData
    ): void {
        $testConfig = new Config(new EntityConfigId('test', 'Test\Entity'));
        if ($immutable !== null) {
            $testConfig->set('immutable', $immutable);
        }
        $this->testConfigProvider->expects(self::any())
            ->method('hasConfig')
            ->with('Test\Entity', null)
            ->willReturn(true);
        $this->testConfigProvider->expects(self::any())
            ->method('getConfig')
            ->with('Test\Entity', null)
            ->willReturn($testConfig);

        $data = $this->doTestSubmit(
            'items',
            MultipleAssociationChoiceType::class,
            [
                'config_id'         => new EntityConfigId('test', 'Test\Entity'),
                'association_class' => 'test'
            ],
            [
                'grouping' => $this->groupingConfigProvider,
                'entity'   => $this->entityConfigProvider,
            ],
            $newVal,
            $oldVal,
            $state,
            $isSetStateExpected
        );

        self::assertEquals($expectedData, $data);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function submitProvider(): array
    {
        return [
            'empty, no changes, oldVal is null'              => [
                'newVal'             => [],
                'oldVal'             => null,
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => false,
                'immutable'          => [],
                'expectedData'       => []
            ],
            'empty, no changes'                              => [
                'newVal'             => [],
                'oldVal'             => [],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => false,
                'immutable'          => [],
                'expectedData'       => []
            ],
            'no changes'                                     => [
                'newVal'             => ['Test\Entity3'],
                'oldVal'             => ['Test\Entity3'],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => false,
                'immutable'          => [],
                'expectedData'       => ['Test\Entity3']
            ],
            'remove item, empty result'                      => [
                'newVal'             => [],
                'oldVal'             => ['Test\Entity3'],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => true,
                'immutable'          => [],
                'expectedData'       => []
            ],
            'remove item'                                    => [
                'newVal'             => ['Test\Entity3'],
                'oldVal'             => ['Test\Entity3', 'Test\Entity4'],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => true,
                'immutable'          => [],
                'expectedData'       => ['Test\Entity3']
            ],
            'add item to null'                               => [
                'newVal'             => ['Test\Entity3'],
                'oldVal'             => null,
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => true,
                'immutable'          => [],
                'expectedData'       => ['Test\Entity3']
            ],
            'add item to empty'                              => [
                'newVal'             => ['Test\Entity3'],
                'oldVal'             => [],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => true,
                'immutable'          => [],
                'expectedData'       => ['Test\Entity3']
            ],
            'add item'                                       => [
                'newVal'             => ['Test\Entity3', 'Test\Entity4'],
                'oldVal'             => ['Test\Entity3'],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => true,
                'immutable'          => [],
                'expectedData'       => ['Test\Entity3', 'Test\Entity4']
            ],
            'replace item'                                   => [
                'newVal'             => ['Test\Entity3'],
                'oldVal'             => ['Test\Entity4'],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => true,
                'immutable'          => [],
                'expectedData'       => ['Test\Entity3']
            ],
            'change order of items'                          => [
                'newVal'             => ['Test\Entity4', 'Test\Entity3'],
                'oldVal'             => ['Test\Entity3', 'Test\Entity4'],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => false,
                'immutable'          => [],
                'expectedData'       => ['Test\Entity3', 'Test\Entity4']
            ],
            'has changes, but state is already STATE_UPDATE' => [
                'newVal'             => ['Test\Entity3'],
                'oldVal'             => [],
                'state'              => ExtendScope::STATE_UPDATE,
                'isSetStateExpected' => false,
                'immutable'          => [],
                'expectedData'       => ['Test\Entity3']
            ],
            'with immutable'                                 => [
                'newVal'             => ['Test\Entity5', 'Test\Entity4'],
                'oldVal'             => ['Test\Entity3', 'Test\Entity5'],
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => true,
                'immutable'          => ['Test\Entity3'],
                'expectedData'       => ['Test\Entity3', 'Test\Entity5', 'Test\Entity4']
            ],
            'with immutable, oldVal is null'                 => [
                'newVal'             => ['Test\Entity4', 'Test\Entity5'],
                'oldVal'             => null,
                'state'              => ExtendScope::STATE_ACTIVE,
                'isSetStateExpected' => true,
                'immutable'          => ['Test\Entity3'],
                'expectedData'       => ['Test\Entity4', 'Test\Entity5']
            ],
        ];
    }

    public function testFinishViewNoConfig(): void
    {
        $this->configManager->expects(self::any())
            ->method('getProvider')
            ->with('test')
            ->willReturn($this->testConfigProvider);

        $this->testConfigProvider->expects(self::once())
            ->method('hasConfig')
            ->with('Test\Entity')
            ->willReturn(false);
        $this->testConfigProvider->expects(self::never())
            ->method('getConfig');

        $view = new FormView();
        $form = new Form($this->createMock(FormConfigInterface::class));
        $options = [
            'config_id'         => new EntityConfigId('test', 'Test\Entity'),
            'association_class' => 'test'
        ];

        $view->children[0] = new FormView($view);
        $view->children[1] = new FormView($view);

        $view->children[0]->vars['value'] = 'Test\Entity1';
        $view->children[1]->vars['value'] = 'Test\Entity2';

        $type = $this->getFormType();
        $type->finishView($view, $form, $options);

        self::assertEquals(
            [
                'attr'  => [],
                'value' => 'Test\Entity1'
            ],
            $view->children[0]->vars
        );
        self::assertEquals(
            [
                'attr'  => [],
                'value' => 'Test\Entity2'
            ],
            $view->children[1]->vars
        );
    }

    public function testFinishViewNoImmutable(): void
    {
        $this->configManager->expects(self::any())
            ->method('getProvider')
            ->with('test')
            ->willReturn($this->testConfigProvider);

        $testConfig = new Config(new EntityConfigId('test', 'Test\Entity'));
        $this->testConfigProvider->expects(self::once())
            ->method('hasConfig')
            ->with('Test\Entity')
            ->willReturn(true);
        $this->testConfigProvider->expects(self::once())
            ->method('getConfig')
            ->with('Test\Entity')
            ->willReturn($testConfig);

        $view = new FormView();
        $form = new Form($this->createMock(FormConfigInterface::class));
        $options = [
            'config_id'         => new EntityConfigId('test', 'Test\Entity'),
            'association_class' => 'test'
        ];

        $view->children[0] = new FormView($view);
        $view->children[1] = new FormView($view);

        $view->children[0]->vars['value'] = 'Test\Entity1';
        $view->children[1]->vars['value'] = 'Test\Entity2';

        $type = $this->getFormType();
        $type->finishView($view, $form, $options);

        self::assertEquals(
            [
                'attr'  => [],
                'value' => 'Test\Entity1'
            ],
            $view->children[0]->vars
        );
        self::assertEquals(
            [
                'attr'  => [],
                'value' => 'Test\Entity2'
            ],
            $view->children[1]->vars
        );
    }

    public function testFinishViewWithImmutable(): void
    {
        $this->configManager->expects(self::any())
            ->method('getProvider')
            ->with('test')
            ->willReturn($this->testConfigProvider);

        $testConfig = new Config(new EntityConfigId('test', 'Test\Entity'));
        $testConfig->set('immutable', ['Test\Entity1']);
        $this->testConfigProvider->expects(self::once())
            ->method('hasConfig')
            ->with('Test\Entity')
            ->willReturn(true);
        $this->testConfigProvider->expects(self::once())
            ->method('getConfig')
            ->with('Test\Entity')
            ->willReturn($testConfig);

        $view = new FormView();
        $form = new Form($this->createMock(FormConfigInterface::class));
        $options = [
            'config_id'         => new EntityConfigId('test', 'Test\Entity'),
            'association_class' => 'test'
        ];

        $view->children[0] = new FormView($view);
        $view->children[1] = new FormView($view);

        $view->children[0]->vars['value'] = 'Test\Entity1';
        $view->children[1]->vars['value'] = 'Test\Entity2';

        $type = $this->getFormType();
        $type->finishView($view, $form, $options);

        self::assertEquals(
            [
                'attr'     => [],
                'disabled' => true,
                'value'    => 'Test\Entity1'
            ],
            $view->children[0]->vars
        );
        self::assertEquals(
            [
                'attr'  => [],
                'value' => 'Test\Entity2'
            ],
            $view->children[1]->vars
        );
    }

    public function testFinishViewForDisabled(): void
    {
        $this->configManager->expects(self::any())
            ->method('getProvider')
            ->with('test')
            ->willReturn($this->testConfigProvider);

        $this->testConfigProvider->expects(self::once())
            ->method('hasConfig')
            ->with('Test\Entity')
            ->willReturn(false);
        $this->testConfigProvider->expects(self::never())
            ->method('getConfig');

        $view = new FormView();
        $form = new Form($this->createMock(FormConfigInterface::class));
        $options = [
            'config_id'         => new EntityConfigId('test', 'Test\Entity'),
            'association_class' => 'test'
        ];

        $view->vars['disabled'] = true;

        $view->children[0] = new FormView($view);
        $view->children[1] = new FormView($view);

        $view->children[0]->vars['value'] = 'Test\Entity1';
        $view->children[1]->vars['value'] = 'Test\Entity2';

        $type = $this->getFormType();
        $type->finishView($view, $form, $options);

        self::assertEquals(
            [
                'attr'     => [],
                'disabled' => true,
                'value'    => 'Test\Entity1'
            ],
            $view->children[0]->vars
        );
        self::assertEquals(
            [
                'attr'     => [],
                'disabled' => true,
                'value'    => 'Test\Entity2'
            ],
            $view->children[1]->vars
        );
    }

    public function testGetBlockPrefix(): void
    {
        self::assertEquals('oro_entity_extend_multiple_association_choice', $this->getFormType()->getBlockPrefix());
    }

    public function testGetParent(): void
    {
        self::assertEquals(ChoiceType::class, $this->getFormType()->getParent());
    }
}
