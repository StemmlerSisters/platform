<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Form\Type;

use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Form\Util\ConfigTypeHelper;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Form\Type\TextareaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType as SymfonyTextareaType;

class TextareaTypeTest extends AbstractConfigTypeTestCase
{
    #[\Override]
    protected function getFormType(): AbstractType
    {
        return new TextareaType(
            new ConfigTypeHelper($this->configManager),
            $this->configManager
        );
    }

    /**
     * @dataProvider submitProvider
     */
    public function testSubmit(string $newVal, ?string $oldVal, string $state, bool $isSetStateExpected): void
    {
        $this->doTestSubmit(
            'testAttr',
            TextareaType::class,
            [
                'config_id' => new EntityConfigId('test', 'Test\Entity')
            ],
            [],
            $newVal,
            $oldVal,
            $state,
            $isSetStateExpected
        );
    }

    public function submitProvider(): array
    {
        return [
            ['', null, ExtendScope::STATE_ACTIVE, false],
            ['', '', ExtendScope::STATE_ACTIVE, false],
            ['', 'old', ExtendScope::STATE_ACTIVE, true],
            ['new', '', ExtendScope::STATE_ACTIVE, true],
            ['new', 'old', ExtendScope::STATE_ACTIVE, true],
            ['new', 'old', ExtendScope::STATE_UPDATE, false],
        ];
    }

    public function testGetBlockPrefix(): void
    {
        self::assertEquals('oro_entity_extend_textarea', $this->getFormType()->getBlockPrefix());
    }

    public function testGetParent(): void
    {
        self::assertEquals(SymfonyTextareaType::class, $this->getFormType()->getParent());
    }
}
