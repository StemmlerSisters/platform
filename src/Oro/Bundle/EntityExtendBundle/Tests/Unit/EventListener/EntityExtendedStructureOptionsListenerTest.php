<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\EntityBundle\Event\EntityStructureOptionsEvent;
use Oro\Bundle\EntityBundle\Model\EntityFieldStructure;
use Oro\Bundle\EntityBundle\Model\EntityStructure;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityExtendBundle\EventListener\EntityExtendedStructureOptionsListener;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EntityExtendedStructureOptionsListenerTest extends TestCase
{
    private const CURRENT_RELATION_TYPE = 'CurrentType';

    private DoctrineHelper&MockObject $doctrineHelper;
    private EntityExtendedStructureOptionsListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);

        $this->listener = new EntityExtendedStructureOptionsListener($this->doctrineHelper);
    }

    private function getEntityStructure(string $fieldName, string $relationType): EntityStructure
    {
        $entityStructure = new EntityStructure();
        $entityStructure->setClassName(\stdClass::class);

        $entityFieldStructure = new EntityFieldStructure();
        $entityFieldStructure->setName($fieldName);
        $entityFieldStructure->setRelationType($relationType);
        $entityStructure->addField($entityFieldStructure);

        return $entityStructure;
    }

    /**
     * @dataProvider dataProvider
     */
    public function testOnOptionsRequest(
        string $expectedClass,
        string $expectedFieldName,
        ?string $expectedRelationType,
        string $fieldName,
        string|int $type,
        bool $hasAssociation
    ): void {
        $entityMetadata = $this->createMock(ClassMetadata::class);

        $entityMetadata->expects($this->once())
            ->method('hasAssociation')
            ->with($expectedFieldName)
            ->willReturn($hasAssociation);

        $entityMetadata->expects($this->exactly((int)$hasAssociation))
            ->method('getAssociationMapping')
            ->with($expectedFieldName)
            ->willReturn(['type' => $type]);

        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with($expectedClass)
            ->willReturn(true);

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityMetadataForClass')
            ->with($expectedClass)
            ->willReturn($entityMetadata);

        $event = new EntityStructureOptionsEvent();
        $event->setData([$this->getEntityStructure($fieldName, self::CURRENT_RELATION_TYPE)]);

        $this->listener->onOptionsRequest($event);

        $this->assertEquals([$this->getEntityStructure($fieldName, $expectedRelationType)], $event->getData());
    }

    public function dataProvider(): array
    {
        $processedRelationType = lcfirst(self::CURRENT_RELATION_TYPE);

        return [
            'no association' => [
                'expectedClass' => \stdClass::class,
                'expectedFieldName' => 'SimpleField',
                'expectedRelationType' => $processedRelationType,
                'fieldName' => 'SimpleField',
                'type' => 'simpletype',
                'hasAssociation' => false
            ],
            'custom field' => [
                'expectedClass' => 'OtherClass',
                'expectedFieldName' => 'SimpleField',
                'expectedRelationType' => $processedRelationType,
                'fieldName' => 'OtherClass::SimpleField',
                'type' => 'simpletype',
                'hasAssociation' => false
            ],
            'not supported relation' => [
                'expectedClass' => 'OtherClass',
                'expectedFieldName' => 'SimpleField',
                'expectedRelationType' => $processedRelationType,
                'fieldName' => 'OtherClass::SimpleField',
                'type' => 'SimpleType',
                'hasAssociation' => true
            ],
            'ref-one case' => [
                'expectedClass' => 'OtherClass',
                'expectedFieldName' => 'SimpleField',
                'expectedRelationType' => $processedRelationType,
                'fieldName' => 'OtherClass::SimpleField',
                'type' => ClassMetadata::MANY_TO_ONE,
                'hasAssociation' => false
            ],
            'ref-many case' => [
                'expectedClass' => 'OtherClass',
                'expectedFieldName' => 'SimpleField',
                'expectedRelationType' => $processedRelationType,
                'fieldName' => 'OtherClass::SimpleField',
                'type' => ClassMetadata::MANY_TO_MANY,
                'hasAssociation' => false
            ],
            'with association' => [
                'expectedClass' => 'OtherClass',
                'expectedFieldName' => 'SimpleField',
                'expectedRelationType' => RelationType::MANY_TO_MANY,
                'fieldName' => 'OtherClass::SimpleField',
                'type' => ClassMetadata::MANY_TO_MANY,
                'hasAssociation' => true
            ],
        ];
    }

    public function testOnOptionsRequestForNotManageableEntity(): void
    {
        $entityMetadata = $this->createMock(ClassMetadata::class);
        $entityMetadata->expects($this->never())
            ->method('hasAssociation')
            ->with('field1')
            ->willReturn(false);

        $entityMetadata->expects($this->never())
            ->method('getAssociationMapping');

        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(\stdClass::class)
            ->willReturn(false);

        $this->doctrineHelper->expects($this->never())
            ->method('getEntityMetadata');

        $event = new EntityStructureOptionsEvent();
        $event->setData([$this->getEntityStructure('field1', self::CURRENT_RELATION_TYPE)]);

        $this->listener->onOptionsRequest($event);

        $this->assertEquals([$this->getEntityStructure('field1', 'currentType')], $event->getData());
    }
}
