<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Twig;

use Oro\Bundle\EntityConfigBundle\Config\AttributeConfigHelper;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Twig\DynamicFieldsExtensionAttributeDecorator;
use Oro\Bundle\EntityExtendBundle\Twig\AbstractDynamicFieldsExtension;
use Oro\Bundle\TestFrameworkBundle\Entity\TestActivityTarget;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Testing\Unit\TwigExtensionTestCaseTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DynamicFieldsExtensionAttributeDecoratorTest extends TestCase
{
    use TwigExtensionTestCaseTrait;
    use EntityTrait;

    private const ENTITY_CLASS_NAME = 'entity_class';

    private AbstractDynamicFieldsExtension&MockObject $baseExtension;
    private AttributeConfigHelper&MockObject $attributeConfigHelper;
    private DynamicFieldsExtensionAttributeDecorator $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->baseExtension = $this->createMock(AbstractDynamicFieldsExtension::class);
        $this->attributeConfigHelper = $this->createMock(AttributeConfigHelper::class);

        $container = self::getContainerBuilder()
            ->add('oro_entity_config.config.attributes_config_helper', $this->attributeConfigHelper)
            ->getContainer($this);

        $this->extension = new DynamicFieldsExtensionAttributeDecorator(
            $this->baseExtension,
            $container
        );
    }

    public function testGetField(): void
    {
        $expectedData = [
            'type' => 'bigint',
            'label' => 'SomeLabel',
            'value' => 777
        ];
        $this->baseExtension->expects($this->once())
            ->method('getField')
            ->willReturn($expectedData);

        $entity = $this->getEntity(TestActivityTarget::class);
        $field = $this->getEntity(FieldConfigModel::class);
        $this->assertEquals(
            $expectedData,
            self::callTwigFunction($this->extension, 'oro_get_dynamic_field', [$entity, $field])
        );
    }

    public function getFieldsDataProvider(): array
    {
        return [
            'no attributes' => [
                'fields' => [
                    'extendField1' => [],
                    'extendField2' => [],
                ],
                'entityClass' => self::ENTITY_CLASS_NAME,
                'attributeHelperWiths' => [
                    [self::ENTITY_CLASS_NAME, 'extendField1'],
                    [self::ENTITY_CLASS_NAME, 'extendField2'],
                ],
                'attributeHelperReturns' => [
                    false,
                    false
                ],
                'expectedFields' => [
                    'extendField1' => [],
                    'extendField2' => [],
                ]
            ],
            'attributes and extend fields' => [
                'fields' => [
                    'extendField1' => [],
                    'attribute1' => [],
                    'extendField2' => [],
                    'attribute2' => [],
                ],
                'entityClass' => self::ENTITY_CLASS_NAME,
                'attributeHelperWiths' => [
                    [self::ENTITY_CLASS_NAME, 'extendField1'],
                    [self::ENTITY_CLASS_NAME, 'attribute1'],
                    [self::ENTITY_CLASS_NAME, 'extendField2'],
                    [self::ENTITY_CLASS_NAME, 'attribute2'],
                ],
                'attributeHelperReturns' => [
                    false,
                    true,
                    false,
                    true
                ],
                'expectedFields' => [
                    'extendField1' => [],
                    'extendField2' => [],
                ]
            ],
            'attributes only' => [
                'fields' => [
                    'attribute1' => [],
                    'attribute2' => [],
                ],
                'entityClass' => null,
                'attributeHelperWiths' => [
                    [TestActivityTarget::class, 'attribute1'],
                    [TestActivityTarget::class, 'attribute2'],
                ],
                'attributeHelperReturns' => [
                    true,
                    true
                ],
                'expectedFields' => []
            ],
        ];
    }

    /**
     * @dataProvider getFieldsDataProvider
     */
    public function testGetFields(
        array $fields,
        ?string $entityClass,
        array $attributeHelperWiths,
        array $attributeHelperReturns,
        array $expectedFields
    ): void {
        $entity = $this->getEntity(TestActivityTarget::class);

        $this->baseExtension->expects($this->once())
            ->method('getFields')
            ->with($entity, $entityClass)
            ->willReturn($fields);

        $this->attributeConfigHelper->expects($this->exactly(count($fields)))
            ->method('isFieldAttribute')
            ->withConsecutive(...$attributeHelperWiths)
            ->willReturnOnConsecutiveCalls(...$attributeHelperReturns);

        $this->assertEquals(
            $expectedFields,
            self::callTwigFunction($this->extension, 'oro_get_dynamic_fields', [$entity, $entityClass])
        );
    }
}
