<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityBundle\Provider\DictionaryEntityNameProvider;
use Oro\Bundle\EntityBundle\Tests\Unit\Provider\Fixtures\DictionaryEntity;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityExtendBundle\PropertyAccess;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class DictionaryEntityNameProviderTest extends TestCase
{
    private ConfigManager&MockObject $configManager;
    private ManagerRegistry&MockObject $doctrine;
    private DictionaryEntityNameProvider $entityNameProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->entityNameProvider = new DictionaryEntityNameProvider(
            $this->configManager,
            $this->doctrine,
            PropertyAccess::createPropertyAccessor()
        );
    }

    private function getEntityConfig(string $scope, string $entityClass, array $values = []): Config
    {
        return new Config(
            new EntityConfigId($scope, $entityClass),
            $values
        );
    }

    private function setHasFieldExpectations(string $entityClass, string $fieldName, bool $hasField): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = $this->createMock(ClassMetadata::class);
        $this->doctrine->expects(self::once())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->willReturn($em);
        $em->expects(self::once())
            ->method('getClassMetadata')
            ->with($entityClass)
            ->willReturn($metadata);
        $metadata->expects(self::once())
            ->method('hasField')
            ->with($fieldName)
            ->willReturn($hasField);
    }

    public function testGetNameForNotManageableEntity(): void
    {
        $entity = new DictionaryEntity();
        $entity->setName('testName');
        $entity->setLabel('testLabel');

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(false);

        self::assertFalse(
            $this->entityNameProvider->getName('test', null, $entity)
        );
    }

    public function testGetNameDQLForNotManageableEntity(): void
    {
        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(false);

        self::assertFalse(
            $this->entityNameProvider->getNameDQL('test', null, DictionaryEntity::class, 'e')
        );
    }

    public function testGetNameForNotDictionaryEntity(): void
    {
        $entity = new DictionaryEntity();
        $entity->setName('testName');
        $entity->setLabel('testLabel');

        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class);

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->with('grouping', DictionaryEntity::class)
            ->willReturn($groupingConfig);

        self::assertFalse(
            $this->entityNameProvider->getName('test', null, $entity)
        );
    }

    public function testGetNameDQLForNotDictionaryEntity(): void
    {
        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class);

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::once())
            ->method('getEntityConfig')
            ->with('grouping', DictionaryEntity::class)
            ->willReturn($groupingConfig);

        self::assertFalse(
            $this->entityNameProvider->getNameDQL('test', null, DictionaryEntity::class, 'e')
        );
    }

    public function testGetNameForDictionaryWithoutConfiguredAndDefaultRepresentationField(): void
    {
        $entity = new DictionaryEntity();
        $entity->setName('testName');
        $entity->setLabel('testLabel');

        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class, ['groups' => ['dictionary']]);
        $dictionaryConfig = $this->getEntityConfig('dictionary', DictionaryEntity::class);

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::exactly(2))
            ->method('getEntityConfig')
            ->willReturnMap([
                ['grouping', DictionaryEntity::class, $groupingConfig],
                ['dictionary', DictionaryEntity::class, $dictionaryConfig]
            ]);

        $this->setHasFieldExpectations(DictionaryEntity::class, 'label', false);

        self::assertFalse(
            $this->entityNameProvider->getName('test', null, $entity)
        );
    }

    public function testGetNameDQLForDictionaryWithoutConfiguredAndDefaultRepresentationField(): void
    {
        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class, ['groups' => ['dictionary']]);
        $dictionaryConfig = $this->getEntityConfig('dictionary', DictionaryEntity::class);

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::exactly(2))
            ->method('getEntityConfig')
            ->willReturnMap([
                ['grouping', DictionaryEntity::class, $groupingConfig],
                ['dictionary', DictionaryEntity::class, $dictionaryConfig]
            ]);

        $this->setHasFieldExpectations(DictionaryEntity::class, 'label', false);

        self::assertFalse(
            $this->entityNameProvider->getNameDQL('test', null, DictionaryEntity::class, 'e')
        );
    }

    public function testGetNameForDictionaryWithoutConfiguredRepresentationField(): void
    {
        $entity = new DictionaryEntity();
        $entity->setName('testName');
        $entity->setLabel('testLabel');

        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class, ['groups' => ['dictionary']]);
        $dictionaryConfig = $this->getEntityConfig('dictionary', DictionaryEntity::class);

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::exactly(2))
            ->method('getEntityConfig')
            ->willReturnMap([
                ['grouping', DictionaryEntity::class, $groupingConfig],
                ['dictionary', DictionaryEntity::class, $dictionaryConfig]
            ]);

        $this->setHasFieldExpectations(DictionaryEntity::class, 'label', true);

        self::assertEquals(
            $entity->getLabel(),
            $this->entityNameProvider->getName('test', null, $entity)
        );
    }

    public function testGetNameDQLForDictionaryWithoutConfiguredRepresentationField(): void
    {
        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class, ['groups' => ['dictionary']]);
        $dictionaryConfig = $this->getEntityConfig('dictionary', DictionaryEntity::class);

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::exactly(2))
            ->method('getEntityConfig')
            ->willReturnMap([
                ['grouping', DictionaryEntity::class, $groupingConfig],
                ['dictionary', DictionaryEntity::class, $dictionaryConfig]
            ]);

        $this->setHasFieldExpectations(DictionaryEntity::class, 'label', true);

        self::assertEquals(
            'e.label',
            $this->entityNameProvider->getNameDQL('test', null, DictionaryEntity::class, 'e')
        );
    }

    public function testGetNameForDictionaryWithConfiguredRepresentationField(): void
    {
        $entity = new DictionaryEntity();
        $entity->setName('testName');
        $entity->setLabel('testLabel');

        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class, ['groups' => ['dictionary']]);
        $dictionaryConfig = $this->getEntityConfig(
            'dictionary',
            DictionaryEntity::class,
            ['representation_field' => 'name']
        );

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::exactly(2))
            ->method('getEntityConfig')
            ->willReturnMap([
                ['grouping', DictionaryEntity::class, $groupingConfig],
                ['dictionary', DictionaryEntity::class, $dictionaryConfig]
            ]);

        self::assertEquals(
            $entity->getName(),
            $this->entityNameProvider->getName('test', null, $entity)
        );
    }

    public function testGetNameDQLForDictionaryWithConfiguredRepresentationField(): void
    {
        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class, ['groups' => ['dictionary']]);
        $dictionaryConfig = $this->getEntityConfig(
            'dictionary',
            DictionaryEntity::class,
            ['representation_field' => 'name']
        );

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::exactly(2))
            ->method('getEntityConfig')
            ->willReturnMap([
                ['grouping', DictionaryEntity::class, $groupingConfig],
                ['dictionary', DictionaryEntity::class, $dictionaryConfig]
            ]);

        self::assertEquals(
            'e.name',
            $this->entityNameProvider->getNameDQL('test', null, DictionaryEntity::class, 'e')
        );
    }

    public function testGetNameForDictionaryWithoutConfiguredRepresentationFieldButWithSearchFields(): void
    {
        $entity = new DictionaryEntity();
        $entity->setName('testName');
        $entity->setLabel('testLabel');

        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class, ['groups' => ['dictionary']]);
        $dictionaryConfig = $this->getEntityConfig(
            'dictionary',
            DictionaryEntity::class,
            ['search_fields' => ['name', 'label']]
        );

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::exactly(2))
            ->method('getEntityConfig')
            ->willReturnMap([
                ['grouping', DictionaryEntity::class, $groupingConfig],
                ['dictionary', DictionaryEntity::class, $dictionaryConfig]
            ]);

        self::assertEquals(
            $entity->getName() . ' ' . $entity->getLabel(),
            $this->entityNameProvider->getName('test', null, $entity)
        );
    }

    public function testGetNameDQLForDictionaryWithoutConfiguredRepresentationFieldButWithSearchFields(): void
    {
        $groupingConfig = $this->getEntityConfig('grouping', DictionaryEntity::class, ['groups' => ['dictionary']]);
        $dictionaryConfig = $this->getEntityConfig(
            'dictionary',
            DictionaryEntity::class,
            ['search_fields' => ['name', 'label']]
        );

        $this->configManager->expects(self::once())
            ->method('hasConfig')
            ->with(DictionaryEntity::class)
            ->willReturn(true);
        $this->configManager->expects(self::exactly(2))
            ->method('getEntityConfig')
            ->willReturnMap([
                ['grouping', DictionaryEntity::class, $groupingConfig],
                ['dictionary', DictionaryEntity::class, $dictionaryConfig]
            ]);

        self::assertEquals(
            'CONCAT(e.name, e.label)',
            $this->entityNameProvider->getNameDQL('test', null, DictionaryEntity::class, 'e')
        );
    }
}
