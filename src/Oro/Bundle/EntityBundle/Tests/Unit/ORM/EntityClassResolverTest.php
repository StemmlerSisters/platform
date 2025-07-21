<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ORM;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EntityClassResolverTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private EntityClassResolver $resolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->resolver = new EntityClassResolver($this->doctrine);
    }

    public function testGetEntityClassWithFullClassName(): void
    {
        $testClass = 'Acme\Bundle\SomeBundle\SomeClass';
        $this->assertEquals($testClass, $this->resolver->getEntityClass($testClass));
    }

    public function testGetEntityClassWithInvalidEntityName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->getEntityClass('AcmeSomeBundle:Entity:SomeClass');
    }

    public function testGetEntityClassWithUnsupportedEntityName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->getEntityClass('AcmeSomeBundle:SomeClass');
    }

    public function testIsKnownEntityClassNamespace(): void
    {
        $config = $this->createMock(Configuration::class);
        $config->expects($this->exactly(2))
            ->method('getEntityNamespaces')
            ->willReturn(['AcmeSomeBundle' => 'Acme\Bundle\SomeBundle\Entity']);

        $em = $this->createMock(EntityManager::class);
        $em->expects($this->exactly(2))
            ->method('getConfiguration')
            ->willReturn($config);

        $this->doctrine->expects($this->exactly(2))
            ->method('getManagerNames')
            ->willReturn(['default' => 'service.default']);
        $this->doctrine->expects($this->exactly(2))
            ->method('getManager')
            ->with('default')
            ->willReturn($em);

        $this->assertTrue($this->resolver->isKnownEntityClassNamespace('Acme\Bundle\SomeBundle\Entity'));
        $this->assertFalse($this->resolver->isKnownEntityClassNamespace('Acme\Bundle\AnotherBundle\Entity'));
    }

    public function testIsEntity(): void
    {
        $className = 'Test\Entity';

        $em = $this->createMock(EntityManager::class);
        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->with($className)
            ->willReturn($em);

        $this->assertTrue(
            $this->resolver->isEntity($className)
        );
    }

    public function testIsEntityForNotManageableEntity(): void
    {
        $className = 'Test\Entity';

        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->with($className)
            ->willReturn(null);

        $this->assertFalse(
            $this->resolver->isEntity($className)
        );
    }
}
