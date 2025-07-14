<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ORM;

use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Proxy\Proxy;
use Doctrine\ORM\Query;
use Oro\Bundle\EntityBundle\ORM\OrmConfiguration;
use Oro\Bundle\EntityBundle\ORM\OroEntityManager;
use Oro\Bundle\EntityBundle\ORM\Registry;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Fixtures\TestEntity;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\OroEntityManagerStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RegistryTest extends TestCase
{
    private const TEST_NAMESPACE_ALIAS = 'Test';
    private const TEST_NAMESPACE = 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Fixtures';
    private const TEST_ENTITY_CLASS = TestEntity::class;
    private const TEST_ENTITY_PROXY_CLASS = Proxy::class;

    private ContainerInterface&MockObject $container;
    private Registry $registry;

    #[\Override]
    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);

        $this->registry = new Registry(
            $this->container,
            [''],
            ['default' => 'service.default'],
            '',
            'default'
        );
    }

    private function getManager(): OroEntityManager
    {
        $managerConfiguration = new OrmConfiguration();

        $managerMetadataFactory = $this->createMock(ClassMetadataFactory::class);
        $managerMetadataFactory->expects(self::any())
            ->method('isTransient')
            ->willReturn(false);

        $manager = $this->getMockBuilder(OroEntityManagerStub::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfiguration', 'getMetadataFactory'])
            ->getMock();
        $manager->expects(self::any())
            ->method('getConfiguration')
            ->willReturn($managerConfiguration);
        $manager->expects(self::any())
            ->method('getMetadataFactory')
            ->willReturn($managerMetadataFactory);

        return $manager;
    }

    public function testManagerServiceCache(): void
    {
        $manager1 = $this->getManager();
        $manager2 = $this->getManager();

        $this->container->expects(self::exactly(3))
            ->method('get')
            ->with('service.default')
            ->willReturnOnConsecutiveCalls($manager1, $manager1, $manager2);
        $this->container->expects(self::once())
            ->method('initialized')
            ->willReturnMap([['service.default', true]]);

        self::assertSame($manager1, $this->registry->getManager('default'));
        // test that a manager service cached
        self::assertSame($manager1, $this->registry->getManager('default'));

        self::assertSame($manager2, $this->registry->resetManager('default'));

        self::assertSame($manager2, $this->registry->getManager('default'));
        // test that a manager cached
        self::assertSame($manager2, $this->registry->getManager('default'));
    }

    public function testManagerCache(): void
    {
        $manager1 = $this->getManager();
        $manager2 = $this->getManager();

        $this->container->expects(self::exactly(3))
            ->method('get')
            ->with('service.default')
            ->willReturnOnConsecutiveCalls($manager1, $manager1, $manager2);
        $this->container->expects(self::once())
            ->method('initialized')
            ->willReturnMap([['service.default', true]]);

        self::assertSame($manager1, $this->registry->getManagerForClass(self::TEST_ENTITY_CLASS));
        // test that a manager cached
        self::assertSame($manager1, $this->registry->getManagerForClass(self::TEST_ENTITY_CLASS));

        self::assertSame($manager2, $this->registry->resetManager());

        self::assertSame($manager2, $this->registry->getManagerForClass(self::TEST_ENTITY_CLASS));
        // test that a manager cached
        self::assertSame($manager2, $this->registry->getManagerForClass(self::TEST_ENTITY_CLASS));
    }

    public function testDefaultQueryCacheLifetimeWhenItWasSpecifiedExplicitly(): void
    {
        $defaultQueryCacheLifetime = 3600;

        $manager = $this->getManager();

        $this->container->expects(self::atLeastOnce())
            ->method('get')
            ->with('service.default')
            ->willReturn($manager);

        $this->registry->setDefaultQueryCacheLifetime($defaultQueryCacheLifetime);

        /** @var Query $query */
        $query = $this->registry->getManager('default')
            ->createQuery('SELECT * FROM ' . self::TEST_ENTITY_CLASS);
        self::assertSame($defaultQueryCacheLifetime, $query->getQueryCacheLifetime());
    }

    public function testDefaultQueryCacheLifetimeWhenItWasNotSpecified(): void
    {
        $manager = $this->getManager();

        $this->container->expects(self::atLeastOnce())
            ->method('get')
            ->with('service.default')
            ->willReturn($manager);

        /** @var Query $query */
        $query = $this->registry->getManager('default')
            ->createQuery('SELECT * FROM ' . self::TEST_ENTITY_CLASS);
        self::assertNull($query->getQueryCacheLifetime());
    }

    public function testDefaultQueryCacheLifetimeWhenItWasSetToZero(): void
    {
        $manager = $this->getManager();

        $this->container->expects(self::atLeastOnce())
            ->method('get')
            ->with('service.default')
            ->willReturn($manager);

        $this->registry->setDefaultQueryCacheLifetime(0);

        /** @var Query $query */
        $query = $this->registry->getManager('default')
            ->createQuery('SELECT * FROM ' . self::TEST_ENTITY_CLASS);
        self::assertSame(0, $query->getQueryCacheLifetime());
    }
}
