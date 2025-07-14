<?php

namespace Oro\Bundle\EntityConfigBundle\Tests\Unit\Provider;

use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityConfigBundle\Provider\PropertyConfigContainer;
use Oro\Bundle\EntityConfigBundle\Provider\SerializedFieldProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SerializedFieldProviderTest extends TestCase
{
    protected ConfigProvider&MockObject $extendConfigProvider;
    protected SerializedFieldProvider $serializedFieldProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->extendConfigProvider = $this->createMock(ConfigProvider::class);

        $this->serializedFieldProvider = new SerializedFieldProvider($this->extendConfigProvider);
    }

    private function checkIsSerializedWrongType(): FieldConfigModel
    {
        $fieldConfigModel = new FieldConfigModel('name', 'wrong_type');
        $this->extendConfigProvider->expects($this->never())
            ->method('getPropertyConfig');

        return $fieldConfigModel;
    }

    public function testIsSerializedWrongType(): void
    {
        $fieldConfigModel = $this->checkIsSerializedWrongType();
        $this->assertFalse($this->serializedFieldProvider->isSerialized($fieldConfigModel));
    }

    public function testIsSerializedByDataWrongType(): void
    {
        $fieldConfigModel = $this->checkIsSerializedWrongType();
        $this->assertFalse($this->serializedFieldProvider->isSerializedByData($fieldConfigModel, []));
    }

    private function expectsEmptyPropertiesValues(): FieldConfigModel
    {
        $fieldConfigModel = new FieldConfigModel('name', 'string');
        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getRequiredPropertiesValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->willReturn([]);
        $this->extendConfigProvider->expects($this->once())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);

        return $fieldConfigModel;
    }

    public function testIsSerializedException(): void
    {
        $fieldConfigModel = $this->expectsEmptyPropertiesValues();
        $this->assertFalse($this->serializedFieldProvider->isSerialized($fieldConfigModel));
    }

    public function testIsSerializedByDataException(): void
    {
        $fieldConfigModel = $this->expectsEmptyPropertiesValues();
        $this->assertFalse($this->serializedFieldProvider->isSerializedByData($fieldConfigModel, []));
    }

    public function testIsSerializedByModelFalse(): void
    {
        $fieldConfigModel = new FieldConfigModel('name', 'string');
        $fieldConfigModel->fromArray('attribute', ['sortable' => true]);

        $this->assertExtendConfigProvider();

        $isSerialized = $this->serializedFieldProvider->isSerialized($fieldConfigModel);

        $this->assertFalse($isSerialized);
    }

    public function testIsSerializedByModelTrue(): void
    {
        $fieldConfigModel = new FieldConfigModel('name', 'string');
        $fieldConfigModel->fromArray('attribute', ['sortable' => false, 'enabled' => true]);

        $this->assertExtendConfigProvider();

        $isSerialized = $this->serializedFieldProvider->isSerialized($fieldConfigModel);

        $this->assertTrue($isSerialized);
    }

    public function testIsSerializedByDataFalse(): void
    {
        $fieldConfigModel = new FieldConfigModel('name', 'string');
        $data = ['attribute' => ['sortable' => true]];

        $this->assertExtendConfigProvider();

        $isSerialized = $this->serializedFieldProvider->isSerializedByData($fieldConfigModel, $data);

        $this->assertFalse($isSerialized);
    }

    public function testIsSerializedByDataTrue(): void
    {
        $fieldConfigModel = new FieldConfigModel('name', 'string');
        $data = ['attribute' => ['sortable' => false, 'enabled' => true]];

        $this->assertExtendConfigProvider();

        $isSerialized = $this->serializedFieldProvider->isSerializedByData($fieldConfigModel, $data);

        $this->assertTrue($isSerialized);
    }

    public function allowEmptyDataProvider(): array
    {
        return [
            'empty allowed' => [
                'allowEmpty' => true,
                'isSerialized' => true
            ],
            'empty not allowed' => [
                'allowEmpty' => false,
                'isSerialized' => false
            ]
        ];
    }

    protected function assertExtendConfigProvider(): void
    {
        $propertyConfigContainer = $this->createMock(PropertyConfigContainer::class);
        $propertyConfigContainer->expects($this->once())
            ->method('getRequiredPropertiesValues')
            ->with(PropertyConfigContainer::TYPE_FIELD)
            ->willReturn(
                [
                    'is_serialized' => [
                        [
                            'config_id' => ['scope' => 'attribute'],
                            'code' => 'sortable',
                            'value' => false,
                        ],
                    ],
                ]
            );

        $this->extendConfigProvider->expects($this->once())
            ->method('getPropertyConfig')
            ->willReturn($propertyConfigContainer);
    }
}
