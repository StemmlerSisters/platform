<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetConfig\CompleteDefinition;

use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDefinition\CompleteCustomDataTypeHelper;
use Oro\Bundle\ApiBundle\Processor\GetConfig\CompleteDefinition\CustomDataTypeCompleterInterface;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;

class CompleteCustomDataTypeHelperTest extends CompleteDefinitionHelperTestCase
{
    private ContainerInterface&MockObject $container;
    private CompleteCustomDataTypeHelper $completeAssociationHelper;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ContainerInterface::class);

        $this->completeAssociationHelper = new CompleteCustomDataTypeHelper(
            [
                ['completer1', self::TEST_REQUEST_TYPE],
                ['completer2', 'another'],
                ['completer3', null]
            ],
            $this->container,
            new RequestExpressionMatcher()
        );
    }

    public function testCompleteCustomDataTypesWhenAllCompletersDoNotCompleteField(): void
    {
        $fieldName = 'field1';
        $dataType = 'dataType1';
        $config = $this->createConfigObject([
            'fields' => [
                $fieldName => [
                    'data_type' => $dataType
                ]
            ]
        ]);
        $metadata = $this->getClassMetadataMock(self::TEST_CLASS_NAME);
        $version = self::TEST_VERSION;
        $requestType = new RequestType([self::TEST_REQUEST_TYPE]);

        $completer1 = $this->createMock(CustomDataTypeCompleterInterface::class);
        $completer3 = $this->createMock(CustomDataTypeCompleterInterface::class);
        $this->container->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['completer1', $completer1],
                ['completer3', $completer3]
            ]);

        $completer1->expects(self::once())
            ->method('completeCustomDataType')
            ->with(
                self::identicalTo($metadata),
                self::identicalTo($config),
                $fieldName,
                self::identicalTo($config->getField($fieldName)),
                $dataType,
                $version,
                self::identicalTo($requestType)
            )
            ->willReturn(false);
        $completer3->expects(self::once())
            ->method('completeCustomDataType')
            ->with(
                self::identicalTo($metadata),
                self::identicalTo($config),
                $fieldName,
                self::identicalTo($config->getField($fieldName)),
                $dataType,
                $version,
                self::identicalTo($requestType)
            )
            ->willReturn(false);

        $this->completeAssociationHelper->completeCustomDataTypes(
            $config,
            $metadata,
            $version,
            $requestType
        );
    }

    public function testCompleteCustomDataTypesWhenFirstCompleterDoCompleteField(): void
    {
        $fieldName = 'field1';
        $dataType = 'dataType1';
        $config = $this->createConfigObject([
            'fields' => [
                $fieldName => [
                    'data_type' => $dataType
                ]
            ]
        ]);
        $metadata = $this->getClassMetadataMock(self::TEST_CLASS_NAME);
        $version = self::TEST_VERSION;
        $requestType = new RequestType([self::TEST_REQUEST_TYPE]);

        $completer1 = $this->createMock(CustomDataTypeCompleterInterface::class);
        $completer3 = $this->createMock(CustomDataTypeCompleterInterface::class);
        $this->container->expects(self::once())
            ->method('get')
            ->with('completer1')
            ->willReturn($completer1);

        $completer1->expects(self::once())
            ->method('completeCustomDataType')
            ->with(
                self::identicalTo($metadata),
                self::identicalTo($config),
                $fieldName,
                self::identicalTo($config->getField($fieldName)),
                $dataType,
                $version,
                self::identicalTo($requestType)
            )
            ->willReturn(true);
        $completer3->expects(self::never())
            ->method('completeCustomDataType');

        $this->completeAssociationHelper->completeCustomDataTypes(
            $config,
            $metadata,
            $version,
            $requestType
        );
    }
}
