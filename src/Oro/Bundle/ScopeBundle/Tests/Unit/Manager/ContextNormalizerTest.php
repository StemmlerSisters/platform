<?php

namespace Oro\Bundle\ScopeBundle\Tests\Unit\Manager;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\ScopeBundle\Manager\ContextNormalizer;
use Oro\Bundle\ScopeBundle\Manager\ScopeManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContextNormalizerTest extends TestCase
{
    private ContextNormalizer $contextNormalizer;
    private ManagerRegistry&MockObject $registry;
    private ScopeManager&MockObject $scopeManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->scopeManager = $this->createMock(ScopeManager::class);
        $this->contextNormalizer = new ContextNormalizer($this->scopeManager, $this->registry);
    }

    public function testNormalizeContext(): void
    {
        $entity1 = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getId'])
            ->getMock();
        $entity1->expects($this->once())
            ->method('getId')
            ->willReturn(101);

        $entity2 = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getId'])
            ->getMock();
        $entity2->expects($this->once())
            ->method('getId')
            ->willReturn(102);
        $context = ['entity_1' => $entity1, 'entity_2' => $entity2];

        $this->assertEquals(
            ['entity_1' => 101, 'entity_2' => 102],
            $this->contextNormalizer->normalizeContext($context)
        );
    }

    public function testDenormalizeContext(): void
    {
        $entity = $this->createMock(\stdClass::class);

        $entities = ['entity' => 'FooEntity'];
        $context = ['entity' => 100];
        $scopeType = 'custom_scope_type';

        $this->scopeManager->expects($this->once())
            ->method('getScopeEntities')
            ->with($scopeType)
            ->willReturn($entities);

        $manager = $this->createMock(ObjectManager::class);
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('FooEntity')
            ->willReturn($manager);

        $manager->expects($this->once())
            ->method('find')
            ->with('FooEntity', 100)
            ->willReturn($entity);

        $expectedContext = ['entity' => $entity];
        $this->assertEquals(
            $expectedContext,
            $this->contextNormalizer->denormalizeContext($scopeType, $context)
        );
    }

    public function testDenormalizeContextWithNonExistentEntity(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Entity foo_entity with identifier 100 does not exist.');

        $entities = ['foo_entity' => 'FooEntity'];
        $context = ['foo_entity' => 100];
        $scopeType = 'custom_scope_type';

        $this->scopeManager->expects($this->once())
            ->method('getScopeEntities')
            ->with($scopeType)
            ->willReturn($entities);

        $manager = $this->createMock(ObjectManager::class);
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('FooEntity')
            ->willReturn($manager);

        $manager->expects($this->once())
            ->method('find')
            ->with('FooEntity', 100)
            ->willReturn(null);

        $this->contextNormalizer->denormalizeContext($scopeType, $context);
    }
}
