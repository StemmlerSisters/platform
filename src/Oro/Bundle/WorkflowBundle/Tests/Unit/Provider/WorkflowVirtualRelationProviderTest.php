<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Provider;

use Doctrine\ORM\Query\Expr\Join;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\WorkflowBundle\Entity\Repository\WorkflowDefinitionRepository;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowStep;
use Oro\Bundle\WorkflowBundle\Provider\WorkflowVirtualRelationProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class WorkflowVirtualRelationProviderTest extends TestCase
{
    private DoctrineHelper&MockObject $doctrineHelper;
    private CacheInterface&MockObject $entitiesWithWorkflowCache;
    private WorkflowVirtualRelationProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->entitiesWithWorkflowCache = $this->createMock(CacheInterface::class);

        $this->provider = new WorkflowVirtualRelationProvider(
            $this->doctrineHelper,
            $this->entitiesWithWorkflowCache
        );
    }

    public function testIsVirtualRelationAndUnknownRelationFieldName(): void
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifierFieldName');
        $this->entitiesWithWorkflowCache->expects($this->never())
            ->method($this->anything());

        $this->assertFalse($this->provider->isVirtualRelation('stdClass', 'unknown_relation'));
    }

    public function testIsVirtualRelationAndNoApplicableWorkflows(): void
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifierFieldName');

        $this->entitiesWithWorkflowCache->expects($this->once())
            ->method('get')
            ->with(WorkflowVirtualRelationProvider::ENTITIES_WITH_WORKFLOW)
            ->willReturn([]);

        $this->assertFalse(
            $this->provider->isVirtualRelation('stdClass', WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME)
        );
    }

    /**
     * @dataProvider fieldDataProvider
     */
    public function testIsVirtualRelationAndKnownRelation(string $field): void
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifierFieldName');

        $class = 'stdClass';
        $this->assertGetEntitiesWithoutCacheCall($class);

        $this->assertTrue($this->provider->isVirtualRelation($class, $field));
    }

    public function fieldDataProvider(): array
    {
        return [
            'item' => [WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME],
            'step' => [WorkflowVirtualRelationProvider::STEPS_RELATION_NAME]
        ];
    }

    public function testGetVirtualRelationsAndNoApplicableWorkflows(): void
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifierFieldName');

        $this->entitiesWithWorkflowCache->expects($this->once())
            ->method('get')
            ->with(WorkflowVirtualRelationProvider::ENTITIES_WITH_WORKFLOW)
            ->willReturn([]);

        $this->assertEquals([], $this->provider->getVirtualRelations('stdClass'));
    }

    public function testGetVirtualRelationsCachedEntitiesWithWorkflows(): void
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifierFieldName');

        $this->entitiesWithWorkflowCache->expects($this->once())
            ->method('get')
            ->with(WorkflowVirtualRelationProvider::ENTITIES_WITH_WORKFLOW)
            ->willReturn(['stdClass' => true]);

        $this->assertEquals(
            [
                WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME => [
                    'label' => 'oro.workflow.workflowitem.entity_label',
                    'relation_type' => 'OneToMany',
                    'related_entity_name' => WorkflowItem::class,
                ],
                WorkflowVirtualRelationProvider::STEPS_RELATION_NAME => [
                    'label' => 'oro.workflow.workflowstep.entity_label',
                    'relation_type' => 'OneToMany',
                    'related_entity_name' => WorkflowStep::class,
                ],
            ],
            $this->provider->getVirtualRelations('stdClass')
        );
    }

    public function testGetVirtualRelationsNotCachedEntitiesWithWorkflows(): void
    {
        $className = 'stdClass';

        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifierFieldName');
        $this->assertGetEntitiesWithoutCacheCall($className);

        $this->assertEquals(
            [
                WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME => [
                    'label' => 'oro.workflow.workflowitem.entity_label',
                    'relation_type' => 'OneToMany',
                    'related_entity_name' => WorkflowItem::class,
                ],
                WorkflowVirtualRelationProvider::STEPS_RELATION_NAME => [
                    'label' => 'oro.workflow.workflowstep.entity_label',
                    'relation_type' => 'OneToMany',
                    'related_entity_name' => WorkflowStep::class,
                ],
            ],
            $this->provider->getVirtualRelations('stdClass')
        );
    }

    public function testGetVirtualRelationQueryAndNoApplicableWorkflows(): void
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifierFieldName');

        $this->assertGetEntitiesWithoutCacheCall();

        $this->assertEquals(
            [],
            $this->provider->getVirtualRelationQuery('stdClass', WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME)
        );
    }

    public function testGetVirtualRelationQueryAndUnknownRelationFieldName(): void
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifierFieldName');
        $this->entitiesWithWorkflowCache->expects($this->never())
            ->method($this->anything());

        $this->assertEquals([], $this->provider->getVirtualRelationQuery('stdClass', 'unknown_field'));
    }

    public function testGetVirtualRelationQuery(): void
    {
        $this->doctrineHelper->expects($this->once())
            ->method('getSingleEntityIdentifierFieldName')
            ->with('stdClass')
            ->willReturn('id');

        $this->entitiesWithWorkflowCache->expects($this->once())
            ->method('get')
            ->with(WorkflowVirtualRelationProvider::ENTITIES_WITH_WORKFLOW)
            ->willReturn(['stdClass' => true]);

        $this->assertEquals(
            [
                'join' => [
                    'left' => [
                        [
                            'join' => WorkflowItem::class,
                            'alias' => WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME,
                            'conditionType' => Join::WITH,
                            'condition' => sprintf(
                                'CAST(entity.%s as string) = CAST(%s.entityId as string) AND %s.entityClass = \'%s\'',
                                'id',
                                WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME,
                                WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME,
                                'stdClass'
                            )
                        ],
                        [
                            'join' => sprintf('%s.currentStep', WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME),
                            'alias' => WorkflowVirtualRelationProvider::STEPS_RELATION_NAME,
                        ]
                    ]
                ]
            ],
            $this->provider->getVirtualRelationQuery('stdClass', WorkflowVirtualRelationProvider::ITEMS_RELATION_NAME)
        );
    }

    public function testGetTargetJoinAlias(): void
    {
        $this->assertEquals('virtual_relation', $this->provider->getTargetJoinAlias('', 'virtual_relation'));
    }

    private function assertGetEntitiesWithoutCacheCall(?string $class = null): void
    {
        $classes = [];
        $expectedClasses = [];
        if ($class) {
            $classes[] = $class;
            $expectedClasses[$class] = true;
        }
        $repo = $this->createMock(WorkflowDefinitionRepository::class);
        $repo->expects($this->once())
            ->method('getAllRelatedEntityClasses')
            ->with(true)
            ->willReturn($classes);

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityRepository')
            ->with(WorkflowDefinition::class)
            ->willReturn($repo);

        $this->entitiesWithWorkflowCache->expects($this->once())
            ->method('get')
            ->with(WorkflowVirtualRelationProvider::ENTITIES_WITH_WORKFLOW)
            ->willReturnCallback(function ($cacheKey, $callback) {
                $item = $this->createMock(ItemInterface::class);
                return $callback($item);
            });
    }
}
