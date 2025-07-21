<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ORM;

use Oro\Bundle\EntityBundle\Exception\EntityAliasNotFoundException;
use Oro\Bundle\EntityBundle\Model\EntityAlias;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\EntityBundle\Provider\EntityAliasLoader;
use Oro\Bundle\EntityBundle\Provider\EntityAliasStorage;
use Oro\Component\Config\Cache\ConfigCacheStateInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class EntityAliasResolverTest extends TestCase
{
    private EntityAliasLoader&MockObject $loader;
    private CacheItemPoolInterface&MockObject $cache;
    private CacheItemInterface&MockObject $cacheItem;
    private LoggerInterface&MockObject $logger;
    private ConfigCacheStateInterface&MockObject $configCacheState;
    private EntityAliasResolver $entityAliasResolver;

    #[\Override]
    protected function setUp(): void
    {
        $this->loader = $this->createMock(EntityAliasLoader::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cacheItem = $this->createMock(CacheItemInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->configCacheState = $this->createMock(ConfigCacheStateInterface::class);

        $this->entityAliasResolver = new EntityAliasResolver(
            $this->loader,
            $this->cache,
            $this->logger
        );
    }

    private function setLoadExpectations()
    {
        $this->cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $this->cache->expects(self::atLeastOnce())
            ->method('getItem')
            ->with('entity_aliases')
            ->willReturn($this->cacheItem);

        $this->loader->expects(self::once())
            ->method('load')
            ->willReturnCallback(function (EntityAliasStorage $storage) {
                $storage->addEntityAlias(
                    'Test\Entity1',
                    new EntityAlias('entity1_alias', 'entity1_plural_alias')
                );
            });
    }

    public function testHasAliasForUnknownEntity(): void
    {
        $this->setLoadExpectations();

        self::assertFalse(
            $this->entityAliasResolver->hasAlias('Test\UnknownEntity')
        );
    }

    public function testGetAliasForUnknownEntity(): void
    {
        $this->expectException(EntityAliasNotFoundException::class);
        $this->expectExceptionMessage('An alias for "Test\UnknownEntity" entity not found.');

        $this->setLoadExpectations();

        $this->entityAliasResolver->getAlias('Test\UnknownEntity');
    }

    public function testGetPluralAliasForUnknownEntity(): void
    {
        $this->expectException(EntityAliasNotFoundException::class);
        $this->expectExceptionMessage('A plural alias for "Test\UnknownEntity" entity not found.');

        $this->setLoadExpectations();

        $this->entityAliasResolver->getPluralAlias('Test\UnknownEntity');
    }

    public function testGetClassByAliasForUnknownAlias(): void
    {
        $this->expectException(EntityAliasNotFoundException::class);
        $this->expectExceptionMessage('The alias "unknown" is not associated with any entity class.');

        $this->setLoadExpectations();

        $this->entityAliasResolver->getClassByAlias('unknown');
    }

    public function testGetClassByPluralAliasForUnknownAlias(): void
    {
        $this->expectException(EntityAliasNotFoundException::class);
        $this->expectExceptionMessage('The plural alias "unknown" is not associated with any entity class.');

        $this->setLoadExpectations();

        $this->entityAliasResolver->getClassByPluralAlias('unknown');
    }

    public function testHasAlias(): void
    {
        $this->setLoadExpectations();

        self::assertTrue(
            $this->entityAliasResolver->hasAlias('Test\Entity1')
        );
    }

    public function testGetAlias(): void
    {
        $this->setLoadExpectations();

        self::assertEquals(
            'entity1_alias',
            $this->entityAliasResolver->getAlias('Test\Entity1')
        );
    }

    public function testGetPluralAlias(): void
    {
        $this->setLoadExpectations();

        self::assertEquals(
            'entity1_plural_alias',
            $this->entityAliasResolver->getPluralAlias('Test\Entity1')
        );
    }

    public function testGetClassByAlias(): void
    {
        $this->setLoadExpectations();

        self::assertEquals(
            'Test\Entity1',
            $this->entityAliasResolver->getClassByAlias('entity1_alias')
        );
    }

    public function testGetClassByPluralAlias(): void
    {
        $this->setLoadExpectations();

        self::assertEquals(
            'Test\Entity1',
            $this->entityAliasResolver->getClassByPluralAlias('entity1_plural_alias')
        );
    }

    public function testGetAll(): void
    {
        $this->setLoadExpectations();

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testWarmUpCache(): void
    {
        $this->cache->expects(self::once())
            ->method('deleteItem')
            ->with('entity_aliases');

        $this->setLoadExpectations();

        $this->entityAliasResolver->warmUpCache();
    }

    public function testClearCache(): void
    {
        $this->cache->expects(self::once())
            ->method('deleteItem')
            ->with('entity_aliases');

        $this->entityAliasResolver->clearCache();
    }

    public function testLoad(): void
    {
        $loadedStorage = new EntityAliasStorage();
        $loadedStorage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);
        $this->cache->expects(self::atLeastOnce())
            ->method('getItem')
            ->with('entity_aliases')
            ->willReturn($this->cacheItem);

        $this->cacheItem->expects($this->once())
            ->method('set')
            ->with([null, $loadedStorage]);
        $this->cache->expects(self::once())
            ->method('save')
            ->with($this->cacheItem);

        $this->loader->expects(self::once())
            ->method('load')
            ->willReturnCallback(function (EntityAliasStorage $storage) {
                $storage->addEntityAlias(
                    'Test\Entity1',
                    new EntityAlias('entity1_alias', 'entity1_plural_alias')
                );
            });

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testLoadFromCache(): void
    {
        $storage = new EntityAliasStorage();
        $storage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('entity_aliases')
            ->willReturn($this->cacheItem);
        $this->cacheItem->expects($this->once())
            ->method('get')
            ->willReturn([null, $storage]);

        $this->loader->expects(self::never())
            ->method('load');

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testLoadFromCacheWithConfigCacheStateWhenConfigCacheTimestampIsNull(): void
    {
        $this->entityAliasResolver->setConfigCacheState($this->configCacheState);

        $storage = new EntityAliasStorage();
        $storage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('entity_aliases')
            ->willReturn($this->cacheItem);
        $this->cacheItem->expects($this->once())
            ->method('get')
            ->willReturn([null, $storage]);

        $this->configCacheState->expects(self::once())
            ->method('isCacheFresh')
            ->with(self::isNull())
            ->willReturn(true);

        $this->loader->expects(self::never())
            ->method('load');

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testLoadFromCacheWithConfigCacheStateWhenConfigCacheIsFresh(): void
    {
        $this->entityAliasResolver->setConfigCacheState($this->configCacheState);

        $timestamp = 123;

        $storage = new EntityAliasStorage();
        $storage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('entity_aliases')
            ->willReturn($this->cacheItem);
        $this->cacheItem->expects($this->once())
            ->method('get')
            ->willReturn([$timestamp, $storage]);

        $this->configCacheState->expects(self::once())
            ->method('isCacheFresh')
            ->with($timestamp)
            ->willReturn(true);

        $this->loader->expects(self::never())
            ->method('load');

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }

    public function testLoadFromCacheWithConfigCacheStateWhenConfigCacheIsDirty(): void
    {
        $this->entityAliasResolver->setConfigCacheState($this->configCacheState);

        $previousTimestamp = 123;
        $newTimestamp = 124;

        $cachedStorage = new EntityAliasStorage();
        $cachedStorage->addEntityAlias('Test\Entity2', new EntityAlias('entity2_alias', 'entity2_plural_alias'));

        $loadedStorage = new EntityAliasStorage();
        $loadedStorage->addEntityAlias('Test\Entity1', new EntityAlias('entity1_alias', 'entity1_plural_alias'));

        $this->cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $this->cache->expects($this->atLeastOnce())
            ->method('getItem')
            ->with('entity_aliases')
            ->willReturn($this->cacheItem);
        $this->cacheItem->expects($this->once())
            ->method('get')
            ->willReturn([$previousTimestamp, $cachedStorage]);
        $this->cacheItem->expects($this->once())
            ->method('set')
            ->with([$newTimestamp, $loadedStorage]);
        $this->cache->expects(self::once())
            ->method('save')
            ->with($this->cacheItem);

        $this->configCacheState->expects(self::once())
            ->method('isCacheFresh')
            ->with($previousTimestamp)
            ->willReturn(false);
        $this->configCacheState->expects(self::once())
            ->method('getCacheTimestamp')
            ->willReturn($newTimestamp);

        $this->loader->expects(self::once())
            ->method('load')
            ->willReturnCallback(function (EntityAliasStorage $storage) {
                $storage->addEntityAlias(
                    'Test\Entity1',
                    new EntityAlias('entity1_alias', 'entity1_plural_alias')
                );
            });

        self::assertEquals(
            ['Test\Entity1' => new EntityAlias('entity1_alias', 'entity1_plural_alias')],
            $this->entityAliasResolver->getAll()
        );
    }
}
