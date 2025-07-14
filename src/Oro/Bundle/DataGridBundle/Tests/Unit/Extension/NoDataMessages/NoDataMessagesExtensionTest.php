<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\Extension\NoDataMessages;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datasource\ArrayDatasource\ArrayDatasource;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Extension\NoDataMessages\NoDataMessagesExtension;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\EntityExtendBundle\PropertyAccess;
use Oro\Bundle\SearchBundle\Datagrid\Datasource\SearchDatasource;
use Oro\Bundle\SearchBundle\Provider\AbstractSearchMappingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class NoDataMessagesExtensionTest extends TestCase
{
    private EntityClassResolver&MockObject $entityClassResolver;
    private AbstractSearchMappingProvider&MockObject $mappingProvider;
    private TranslatorInterface&MockObject $translator;
    private NoDataMessagesExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->entityClassResolver = $this->createMock(EntityClassResolver::class);
        $this->mappingProvider = $this->createMock(AbstractSearchMappingProvider::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->extension = new NoDataMessagesExtension(
            $this->entityClassResolver,
            $this->mappingProvider,
            $this->translator
        );
    }

    public function testProcessConfigs(): void
    {
        $config = DatagridConfiguration::create(
            [
                'options' => [
                    'entityHint' => 'entity_hint_key',
                    'noDataMessages' => [
                        'emptyGrid' => 'empty_grid_key',
                        'emptyFilteredGrid' => 'empty_filtered_grid_key'
                    ]
                ]
            ],
            PropertyAccess::createPropertyAccessorWithDotSyntax()
        );

        $expected = [
            'entityHint' => 'Entities',
            'noDataMessages' => [
                'emptyGrid' => 'No entities were found.',
                'emptyFilteredGrid' => 'No entities were found to match your search.'
            ]
        ];

        $this->translator->expects($this->any())
            ->method('trans')
            ->willReturnMap([
                ['entity_hint_key', [], null, null, 'Entities'],
                ['empty_grid_key', [], null, null, 'No entities were found.'],
                ['empty_filtered_grid_key', [], null, null, 'No entities were found to match your search.']
            ]);

        $this->extension->processConfigs($config);

        $this->assertEquals($expected, $config->offsetGetByPath('options'));
    }

    public function testProcessConfigsOrmDatasourceWithoutOptions(): void
    {
        $config = DatagridConfiguration::create(
            [
                'source' => [
                    'type' => OrmDatasource::TYPE,
                    'query' => [
                        'select' => ['entity.id'],
                        'from' => [
                            ['table' => 'entity_table', 'alias' => 'entity']
                        ]
                    ]
                ],
            ],
            PropertyAccess::createPropertyAccessorWithDotSyntax()
        );

        $expected = [
            'entityHint' => 'Entities',
        ];

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('stdclass.entity_plural_label')
            ->willReturn('Entities');

        $this->entityClassResolver->expects($this->once())
            ->method('getEntityClass')
            ->with('entity_table')
            ->willReturn(\stdClass::class);

        $this->extension->processConfigs($config);

        $this->assertEquals($expected, $config->offsetGetByPath('options'));
    }

    public function testProcessConfigsSearchDatasourceWithoutOptions(): void
    {
        $config = DatagridConfiguration::create(
            [
                'source' => [
                    'type' => SearchDatasource::TYPE,
                    'query' => [
                        'select' => ['entity.id'],
                        'from' => ['alias']
                    ]
                ]
            ],
            PropertyAccess::createPropertyAccessorWithDotSyntax()
        );

        $expected = [
            'entityHint' => 'Entities',
        ];

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('stdclass.entity_plural_label')
            ->willReturn('Entities');

        $this->mappingProvider->expects($this->once())
            ->method('getEntityClass')
            ->with('alias')
            ->willReturn(\stdClass::class);

        $this->extension->processConfigs($config);

        $this->assertEquals($expected, $config->offsetGetByPath('options'));
    }

    public function testProcessConfigsArrayDatasourceWithoutOptions(): void
    {
        $config = DatagridConfiguration::create(
            [
                'source' => [
                    'type' => ArrayDatasource::TYPE,
                ]
            ],
            PropertyAccess::createPropertyAccessorWithDotSyntax()
        );

        $this->extension->processConfigs($config);

        $this->assertNull($config->offsetGetByPath('options'));
    }
}
