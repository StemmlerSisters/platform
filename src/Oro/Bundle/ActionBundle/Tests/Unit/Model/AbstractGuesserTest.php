<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Oro\Bundle\ActionBundle\Exception\AttributeException;
use Oro\Bundle\ActionBundle\Model\AbstractGuesser;
use Oro\Bundle\ActionBundle\Provider\DoctrineTypeMappingProvider;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class AbstractGuesserTest extends TestCase
{
    private FormRegistry&MockObject $formRegistry;
    private ManagerRegistry&MockObject $managerRegistry;
    private ConfigProvider&MockObject $entityConfigProvider;
    private ConfigProvider&MockObject $formConfigProvider;
    private DoctrineTypeMappingProvider&MockObject $doctrineTypeMappingProvider;
    private AbstractGuesser $guesser;

    #[\Override]
    protected function setUp(): void
    {
        $this->formRegistry = $this->createMock(FormRegistry::class);
        $this->managerRegistry = $this->getMockForAbstractClass(ManagerRegistry::class);
        $this->entityConfigProvider = $this->createMock(ConfigProvider::class);
        $this->formConfigProvider = $this->createMock(ConfigProvider::class);
        $this->doctrineTypeMappingProvider = $this->createMock(DoctrineTypeMappingProvider::class);

        $this->guesser = $this->getMockBuilder(AbstractGuesser::class)
            ->setConstructorArgs([
                $this->formRegistry,
                $this->managerRegistry,
                $this->entityConfigProvider,
                $this->formConfigProvider
            ])
            ->getMockForAbstractClass();

        $this->guesser->setDoctrineTypeMappingProvider($this->doctrineTypeMappingProvider);
    }

    public function testGuessMetadataAndFieldNoEntityManagerException(): void
    {
        $this->expectException(AttributeException::class);
        $this->expectExceptionMessage("Can't get entity manager for class RootClass");

        $rootClass = 'RootClass';

        $this->managerRegistry->expects($this->once())
            ->method('getManagerForClass')
            ->with($rootClass)
            ->willReturn(null);

        $this->guesser->guessMetadataAndField($rootClass, 'entity.field');
    }

    public function testAddDoctrineTypeMapping(): void
    {
        $doctrineType = 'date';
        $attributeType = 'object';
        $attributeOptions = ['class' => 'DateTime'];

        $this->guesser->setDoctrineTypeMappingProvider(null);
        $this->guesser->addDoctrineTypeMapping($doctrineType, $attributeType, $attributeOptions);

        $propertyPath = 'entity.field';
        $rootClass = 'RootClass';
        $fieldLabel = 'Field Label';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects(self::any())
            ->method('getName')
            ->willReturn($rootClass);
        $metadata->expects(self::any())
            ->method('hasAssociation')
            ->with('field')
            ->willReturn(false);
        $metadata->expects(self::any())
            ->method('hasField')
            ->with('field')
            ->willReturn(true);
        $metadata->expects(self::any())
            ->method('getTypeOfField')
            ->with('field')
            ->willReturn('date');

        $this->setEntityMetadata([$rootClass => $metadata]);
        $this->setEntityConfigProvider($rootClass, 'field', false, true, $fieldLabel);

        $this->assertAttributeOptions(
            $this->guesser->guessParameters($rootClass, $propertyPath),
            $fieldLabel,
            'object',
            ['class' => 'DateTime']
        );
    }

    public function testGuessMetadataAndFieldOneElement(): void
    {
        $this->assertNull($this->guesser->guessMetadataAndField('TestEntity', 'single_element_path'));
        $this->assertNull($this->guesser->guessMetadataAndField('TestEntity', new PropertyPath('single_element_path')));
    }

    public function testGuessMetadataAndFieldInvalidComplexPath(): void
    {
        $propertyPath = 'entity.unknown_field';
        $rootClass = 'RootClass';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())
            ->method('hasAssociation')
            ->with('unknown_field')
            ->willReturn(false);
        $metadata->expects($this->once())
            ->method('hasField')
            ->with('unknown_field')
            ->willReturn(false);

        $this->setEntityMetadata([$rootClass => $metadata]);

        $this->assertNull($this->guesser->guessMetadataAndField($rootClass, $propertyPath));
    }

    public function testGuessMetadataAndFieldAssociationField(): void
    {
        $propertyPath = 'entity.association';
        $rootClass = 'RootClass';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->once())
            ->method('hasAssociation')
            ->with('association')
            ->willReturn(true);

        $this->setEntityMetadata([$rootClass => $metadata]);

        $this->assertEquals(
            ['metadata' => $metadata, 'field' => 'association'],
            $this->guesser->guessMetadataAndField($rootClass, $propertyPath)
        );
    }

    public function testGuessMetadataAndFieldSecondLevelAssociation(): void
    {
        $propertyPath = 'entity.association.field';
        $rootClass = 'RootClass';
        $associationEntity = 'AssociationEntity';

        $entityMetadata = $this->createMock(ClassMetadata::class);
        $entityMetadata->expects($this->any())
            ->method('hasAssociation')
            ->with('association')
            ->willReturn(true);
        $entityMetadata->expects($this->once())
            ->method('getAssociationTargetClass')
            ->with('association')
            ->willReturn($associationEntity);

        $associationMetadata = $this->createMock(ClassMetadata::class);
        $associationMetadata->expects($this->once())
            ->method('hasAssociation')
            ->with('field')
            ->willReturn(false);
        $associationMetadata->expects($this->once())
            ->method('hasField')
            ->with('field')
            ->willReturn(true);

        $this->setEntityMetadata([$rootClass => $entityMetadata, $associationEntity => $associationMetadata]);

        $this->assertEquals(
            ['metadata' => $associationMetadata, 'field' => 'field'],
            $this->guesser->guessMetadataAndField($rootClass, $propertyPath)
        );
    }

    public function testGuessParametersNoMetadataAndFieldGuess(): void
    {
        $this->assertNull($this->guesser->guessParameters('TestEntity', 'single_element_path'));
    }

    public function testGuessParametersFieldWithoutMapping(): void
    {
        $propertyPath = 'entity.field';
        $rootClass = 'RootClass';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->any())
            ->method('hasAssociation')
            ->with('field')
            ->willReturn(false);
        $metadata->expects($this->any())
            ->method('hasField')
            ->with('field')
            ->willReturn(true);
        $metadata->expects($this->any())
            ->method('getTypeOfField')
            ->with('field')
            ->willReturn('not_existing_type');

        $this->setEntityMetadata([$rootClass => $metadata]);

        $this->doctrineTypeMappingProvider->expects($this->any())
            ->method('getDoctrineTypeMappings')
            ->willReturn([]);

        $this->assertNull($this->guesser->guessParameters($rootClass, $propertyPath));
    }

    public function testGuessParametersFieldWithMapping(): void
    {
        $propertyPath = 'entity.field';
        $rootClass = 'RootClass';
        $fieldLabel = 'Field Label';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->any())
            ->method('getName')
            ->willReturn($rootClass);
        $metadata->expects($this->any())
            ->method('hasAssociation')
            ->with('field')
            ->willReturn(false);
        $metadata->expects($this->any())
            ->method('hasField')
            ->with('field')
            ->willReturn(true);
        $metadata->expects($this->any())
            ->method('getTypeOfField')
            ->with('field')
            ->willReturn('date');

        $this->setEntityMetadata([$rootClass => $metadata]);
        $this->setEntityConfigProvider($rootClass, 'field', false, true, $fieldLabel);

        $this->doctrineTypeMappingProvider->expects($this->any())
            ->method('getDoctrineTypeMappings')
            ->willReturn(
                [
                    'date' => [
                        'type' => 'object',
                        'options' => ['class' => 'DateTime']
                    ]
                ]
            );

        $this->assertAttributeOptions(
            $this->guesser->guessParameters($rootClass, $propertyPath),
            $fieldLabel,
            'object',
            ['class' => 'DateTime']
        );
    }

    public function testGuessParametersSingleAssociation(): void
    {
        $propertyPath = 'entity.association';
        $rootClass = 'RootClass';
        $associationClass = 'AssociationClass';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->any())
            ->method('getName')
            ->willReturn($rootClass);
        $metadata->expects($this->any())
            ->method('hasAssociation')
            ->with('association')
            ->willReturn(true);
        $metadata->expects($this->any())
            ->method('hasField')
            ->with('association')
            ->willReturn(false);
        $metadata->expects($this->any())
            ->method('isCollectionValuedAssociation')
            ->with('association')
            ->willReturn(false);
        $metadata->expects($this->any())
            ->method('getAssociationTargetClass')
            ->with('association')
            ->willReturn($associationClass);

        $this->setEntityMetadata([$rootClass => $metadata]);
        $this->setEntityConfigProvider($rootClass, 'association', false, true, null, 'ref-one');

        $this->doctrineTypeMappingProvider->expects($this->any())
            ->method('getDoctrineTypeMappings')
            ->willReturn([]);

        $result = $this->guesser->guessParameters($rootClass, $propertyPath);
        $this->assertAttributeOptions(
            $result,
            null,
            'entity',
            ['class' => $associationClass]
        );
    }

    public function testGuessParametersFieldFromEntityConfig(): void
    {
        $propertyPath = 'entity.field';
        $rootClass = 'RootClass';
        $fieldLabel = 'Field Label';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->any())
            ->method('getName')
            ->willReturn($rootClass);

        $metadata->expects($this->any())
            ->method('hasAssociation')
            ->with('field')
            ->willReturn(false);
        $metadata->expects($this->any())
            ->method('hasField')
            ->with('field')
            ->willReturn(false);

        $this->setEntityMetadata([$rootClass => $metadata]);
        $this->setEntityConfigProvider($rootClass, 'field', false, true, $fieldLabel, 'date');

        $this->doctrineTypeMappingProvider->expects($this->any())
            ->method('getDoctrineTypeMappings')
            ->willReturn(
                [
                    'date' => [
                        'type' => 'object',
                        'options' => ['class' => 'DateTime']
                    ]
                ]
            );

        $this->assertAttributeOptions(
            $this->guesser->guessParameters($rootClass, $propertyPath),
            $fieldLabel,
            'object',
            ['class' => 'DateTime']
        );
    }

    public function testGuessParametersCollectionAssociation(): void
    {
        $propertyPath = 'entity.association';
        $rootClass = 'RootClass';

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->expects($this->any())
            ->method('getName')
            ->willReturn($rootClass);
        $metadata->expects($this->any())
            ->method('hasAssociation')
            ->with('association')
            ->willReturn(true);
        $metadata->expects($this->any())
            ->method('hasField')
            ->with('association')
            ->willReturn(false);
        $metadata->expects($this->any())
            ->method('isCollectionValuedAssociation')
            ->with('association')
            ->willReturn(true);

        $this->setEntityMetadata([$rootClass => $metadata]);
        $this->setEntityConfigProvider($rootClass, 'association', true, false);

        $this->doctrineTypeMappingProvider->expects($this->any())
            ->method('getDoctrineTypeMappings')
            ->willReturn([]);

        $this->assertAttributeOptions(
            $this->guesser->guessParameters($rootClass, $propertyPath),
            null,
            'object',
            ['class' => ArrayCollection::class]
        );
    }

    private function assertAttributeOptions(
        array $actualOptions,
        ?string $label,
        string $type,
        array $options = []
    ): void {
        $this->assertNotNull($actualOptions);
        $this->assertIsArray($actualOptions);
        $this->assertArrayHasKey('label', $actualOptions);
        $this->assertArrayHasKey('type', $actualOptions);
        $this->assertArrayHasKey('options', $actualOptions);
        $this->assertEquals($label, $actualOptions['label']);
        $this->assertEquals($type, $actualOptions['type']);
        $this->assertEquals($options, $actualOptions['options']);
    }

    private function setEntityMetadata(array $metadataArray): void
    {
        $valueMap = [];
        foreach ($metadataArray as $entity => $metadata) {
            $valueMap[] = [$entity, $metadata];
        }

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->any())
            ->method('getClassMetadata')
            ->with($this->isType('string'))
            ->willReturnMap($valueMap);

        $this->managerRegistry->expects($this->any())
            ->method('getManagerForClass')
            ->with($this->isType('string'))
            ->willReturn($entityManager);
    }

    private function setEntityConfigProvider(
        string $class,
        string $field,
        bool $multiple = false,
        bool $hasConfig = true,
        ?string $label = null,
        ?string $fieldType = null
    ): void {
        $labelOption = $multiple ? 'plural_label' : 'label';

        $entityConfig = $this->getMockForAbstractClass(ConfigInterface::class);
        $entityConfig->expects($this->any())
            ->method('has')
            ->with($labelOption)
            ->willReturn(!empty($label));
        $entityConfig->expects($this->any())
            ->method('get')
            ->with($labelOption)
            ->willReturn($label);

        $this->entityConfigProvider->expects($this->any())
            ->method('hasConfig')
            ->with($class, $field)
            ->willReturn($hasConfig);
        $this->entityConfigProvider->expects($this->any())
            ->method('getConfig')
            ->with($class, $field)
            ->willReturn($entityConfig);

        if ($fieldType) {
            $configId = $this->createMock(FieldConfigId::class);
            $entityConfig->expects($this->any())
                ->method('getId')
                ->willReturn($configId);
            $configId->expects($this->any())
                ->method('getFieldType')
                ->willReturn($fieldType);
        }
    }
}
