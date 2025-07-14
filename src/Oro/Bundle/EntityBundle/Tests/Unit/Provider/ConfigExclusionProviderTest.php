<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Provider;

use Doctrine\ORM\Mapping\ClassMetadata;
use Oro\Bundle\EntityBundle\Configuration\EntityConfiguration;
use Oro\Bundle\EntityBundle\Configuration\EntityConfigurationProvider;
use Oro\Bundle\EntityBundle\Provider\ConfigExclusionProvider;
use Oro\Bundle\EntityBundle\Provider\EntityHierarchyProviderInterface;
use PHPUnit\Framework\TestCase;

class ConfigExclusionProviderTest extends TestCase
{
    private ConfigExclusionProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $hierarchyProvider = $this->createMock(EntityHierarchyProviderInterface::class);
        $hierarchyProvider->expects(self::any())
            ->method('getHierarchyForClassName')
            ->willReturnMap([
                ['Test\Entity1', ['Test\BaseEntity1']],
                ['Test\Entity2', []],
                ['Test\Entity3', []],
                ['Test\Entity4', []]
            ]);

        $configProvider = $this->createMock(EntityConfigurationProvider::class);
        $configProvider->expects(self::any())
            ->method('getConfiguration')
            ->with(EntityConfiguration::EXCLUSIONS)
            ->willReturn([
                ['entity' => 'Test\Entity1', 'field' => 'field1'],
                ['entity' => 'Test\BaseEntity1', 'field' => 'field2'],
                ['type' => 'date'],
                ['entity' => 'Test\Entity3']
            ]);

        $this->provider = new ConfigExclusionProvider(
            $hierarchyProvider,
            $configProvider
        );
    }

    /**
     * @dataProvider entityProvider
     */
    public function testIsIgnoredEntity($className, $expected): void
    {
        self::assertEquals(
            $expected,
            $this->provider->isIgnoredEntity($className)
        );
    }

    /**
     * @dataProvider fieldProvider
     */
    public function testIsIgnoredField($metadata, $fieldName, $expected): void
    {
        self::assertEquals(
            $expected,
            $this->provider->isIgnoredField($metadata, $fieldName)
        );
    }

    public function testIsIgnoredFieldByDataType(): void
    {
        $metadata = $this->getEntityMetadata(
            'Test\Entity2',
            [
                'field1' => 'date'
            ]
        );

        self::assertTrue(
            $this->provider->isIgnoredField($metadata, 'field1')
        );
    }

    /**
     * @dataProvider fieldProvider
     */
    public function testIsIgnoredRelation($metadata, $associationName, $expected): void
    {
        self::assertEquals(
            $expected,
            $this->provider->isIgnoredRelation($metadata, $associationName)
        );
    }

    public function entityProvider(): array
    {
        return [
            'excluded'                                    => ['Test\Entity3', true],
            'not excluded, has excluded fields'           => ['Test\Entity1', false],
            'not excluded, does not have excluded fields' => ['Test\Entity4', false]
        ];
    }

    public function fieldProvider(): array
    {
        return [
            'excluded'                                               => [
                $this->getEntityMetadata(
                    'Test\Entity1',
                    [
                        'field1' => 'integer',
                        'field2' => 'string',
                        'field3' => 'date'
                    ]
                ),
                'field1',
                true
            ],
            'excluded in parent class'                               => [
                $this->getEntityMetadata(
                    'Test\Entity1',
                    [
                        'field1' => 'integer',
                        'field2' => 'string',
                        'field3' => 'date'
                    ]
                ),
                'field2',
                true
            ],
            'not excluded, but entity has other excluded fields'     => [
                $this->getEntityMetadata(
                    'Test\Entity1',
                    [
                        'field1' => 'integer',
                        'field2' => 'string',
                        'field3' => 'date',
                        'field4' => 'text'
                    ]
                ),
                'field4',
                false
            ],
            'not excluded, entity does not have any excluded fields' => [
                $this->getEntityMetadata(
                    'Test\Entity2',
                    [
                        'field1' => 'integer',
                        'field2' => 'date'
                    ]
                ),
                'field1',
                false
            ],
            'excluded, because whole entity is excluded'             => [
                $this->getEntityMetadata(
                    'Test\Entity3',
                    [
                        'field1' => 'integer'
                    ]
                ),
                'field1',
                true
            ]
        ];
    }

    private function getEntityMetadata(string $className, array $fields = []): ClassMetadata
    {
        $metadata = new ClassMetadata($className);
        foreach ($fields as $fieldName => $fieldType) {
            $metadata->mapField(['fieldName' => $fieldName, 'type' => $fieldType]);
        }

        return $metadata;
    }
}
