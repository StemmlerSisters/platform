<?php

namespace Oro\Bundle\TagBundle\Tests\Unit\Grid;

use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\Common\MetadataObject;
use Oro\Bundle\DataGridBundle\Datagrid\ParameterBag;
use Oro\Bundle\DataGridBundle\Extension\InlineEditing\Configuration;
use Oro\Bundle\DataGridBundle\Extension\InlineEditing\InlineEditingConfigurator;
use Oro\Bundle\EntityBundle\ORM\EntityClassResolver;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\TagBundle\Entity\TagManager;
use Oro\Bundle\TagBundle\Grid\TagsExtension;
use Oro\Bundle\TagBundle\Helper\TaggableHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class TagsExtensionTest extends TestCase
{
    private TagManager&MockObject $tagManager;
    private EntityClassResolver&MockObject $entityClassResolver;
    private TaggableHelper&MockObject $taggableHelper;
    private AuthorizationCheckerInterface&MockObject $authorizationChecker;
    private TokenStorageInterface&MockObject $tokenStorage;
    private InlineEditingConfigurator&MockObject $inlineEditingConfigurator;
    private FeatureChecker&MockObject $featureChecker;
    private TagsExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->tagManager = $this->createMock(TagManager::class);
        $this->entityClassResolver = $this->createMock(EntityClassResolver::class);
        $this->taggableHelper = $this->createMock(TaggableHelper::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->inlineEditingConfigurator = $this->createMock(InlineEditingConfigurator::class);
        $this->featureChecker = $this->createMock(FeatureChecker::class);

        $this->extension = new TagsExtension(
            $this->tagManager,
            $this->entityClassResolver,
            $this->taggableHelper,
            $this->authorizationChecker,
            $this->tokenStorage,
            $this->inlineEditingConfigurator,
            $this->featureChecker
        );
        $this->extension->setParameters(new ParameterBag());
    }

    public function testGetPriority(): void
    {
        self::assertEquals(10, $this->extension->getPriority());
    }

    public function testVisitMetadata(): void
    {
        $config = DatagridConfiguration::create([
            'extended_entity_name' => 'Test',
            Configuration::BASE_CONFIG_KEY => ['enable' => true]
        ]);
        $data = MetadataObject::create([]);

        $this->inlineEditingConfigurator->expects(self::once())
            ->method('isInlineEditingSupported')
            ->with($config)
            ->willReturn(true);

        $this->extension->visitMetadata($config, $data);
        self::assertEquals(
            $config->offsetGet(Configuration::BASE_CONFIG_KEY),
            $data->offsetGet(Configuration::BASE_CONFIG_KEY)
        );
    }

    /**
     * @dataProvider parametersDataProvider
     */
    public function testIsApplicable(
        array $parameters,
        bool $featureEnabled,
        bool $isTaggable,
        bool $isGranted,
        ?TokenInterface $token,
        bool $expected
    ): void {
        $config = DatagridConfiguration::create($parameters);
        $config->setName('test_grid');

        $this->featureChecker->expects(self::any())
            ->method('isFeatureEnabled')
            ->with('manage_tags')
            ->willReturn($featureEnabled);

        $this->taggableHelper->expects(self::any())
            ->method('isTaggable')
            ->with('Test')
            ->willReturn($isTaggable);
        $this->tokenStorage->expects(self::any())
            ->method('getToken')
            ->willReturn($token);

        $this->authorizationChecker->expects(self::any())
            ->method('isGranted')
            ->with('oro_tag_view')
            ->willReturn($isGranted);

        self::assertEquals($expected, $this->extension->isApplicable($config));
    }

    public function parametersDataProvider(): array
    {
        return [
            [
                ['extended_entity_name' => 'Test', 'source' => ['type' => 'orm'], 'properties' => ['id' => 'id']],
                true,
                true,
                true,
                $this->createMock(TokenInterface::class),
                true
            ],
            [
                ['extended_entity_name' => 'Test', 'source' => ['type' => 'orm'], 'properties' => ['id' => 'id']],
                false,
                true,
                true,
                $this->createMock(TokenInterface::class),
                false
            ],
            [
                ['extended_entity_name' => 'Test', 'source' => ['type' => 'orm'], 'properties' => ['id' => 'id']],
                true,
                false,
                true,
                $this->createMock(TokenInterface::class),
                false
            ],
            [
                ['extended_entity_name' => 'Test', 'source' => ['type' => 'orm'], 'properties' => ['id' => 'id']],
                true,
                true,
                false,
                $this->createMock(TokenInterface::class),
                false
            ],
            [
                ['extended_entity_name' => 'Test', 'source' => ['type' => 'orm'], 'properties' => ['id' => 'id']],
                true,
                true,
                true,
                null,
                false
            ],
            [
                ['extended_entity_name' => null, 'source' => ['type' => 'orm'], 'properties' => ['id' => 'id']],
                true,
                true,
                true,
                $this->createMock(TokenInterface::class),
                false
            ],
            [
                ['extended_entity_name' => 'Test', 'source' => ['type' => 'array'], 'properties' => ['id' => 'id']],
                true,
                true,
                true,
                $this->createMock(TokenInterface::class),
                false
            ],
            [
                ['extended_entity_name' => 'Test', 'source' => ['type' => 'orm'], 'properties' => []],
                true,
                true,
                true,
                $this->createMock(TokenInterface::class),
                false
            ]
        ];
    }

    public function testProcessConfigsWhenInlineEditingEnabled(): void
    {
        $config = DatagridConfiguration::create([
            'extended_entity_name' => 'Test',
            'columns' => [
                'column1' => [
                    'label' => 'Column 1',
                ]
            ],
            'filters' => [
                'columns' => [
                    'id' => []
                ]
            ]
        ]);

        $this->inlineEditingConfigurator->expects(self::once())
            ->method('isInlineEditingSupported')
            ->with($config)
            ->willReturn(true);
        $this->inlineEditingConfigurator->expects(self::once())
            ->method('configureInlineEditingForGrid')
            ->with($config);
        $this->inlineEditingConfigurator->expects(self::once())
            ->method('configureInlineEditingForColumn')
            ->with($config, 'tags');

        $this->extension->processConfigs($config);

        $actualParameters = $config->toArray();
        self::assertArrayHasKey('tags', $actualParameters['columns']);
        self::assertTrue($actualParameters['columns']['tags']['inline_editing']['enable']);
        self::assertArrayHasKey('tagname', $actualParameters['filters']['columns']);
        self::assertTrue($actualParameters['inline_editing']['enable']);
    }

    public function testProcessConfigsWhenInlineEditingDisabledForDatagrid(): void
    {
        $config = DatagridConfiguration::create([
            'extended_entity_name' => 'Test',
            'columns' => [
                'column1' => [
                    'label' => 'Column 1'
                ]
            ],
            'filters' => [
                'columns' => [
                    'id' => []
                ]
            ],
            'inline_editing' => [
                'enable' => false
            ]
        ]);

        $this->inlineEditingConfigurator->expects(self::once())
            ->method('isInlineEditingSupported')
            ->with($config)
            ->willReturn(true);

        $this->inlineEditingConfigurator->expects(self::never())
            ->method('configureInlineEditingForGrid');
        $this->inlineEditingConfigurator->expects(self::never())
            ->method('configureInlineEditingForColumn');

        $this->extension->processConfigs($config);

        $actualParameters = $config->toArray();
        self::assertArrayHasKey('tags', $actualParameters['columns']);
        self::assertArrayHasKey('tagname', $actualParameters['filters']['columns']);
        self::assertFalse($actualParameters['inline_editing']['enable']);
    }

    public function testProcessConfigsWhenInlineEditingDisabledForColumnInDatagrid(): void
    {
        $config = DatagridConfiguration::create([
            'extended_entity_name' => 'Test',
            'columns' => [
                'column1' => [
                    'label' => 'Column 1'
                ],
                'tags' => [
                    'inline_editing' => [
                        'enable' => false
                    ]
                ]
            ],
            'filters' => [
                'columns' => [
                    'id' => []
                ]
            ]
        ]);

        $this->inlineEditingConfigurator->expects(self::once())
            ->method('isInlineEditingSupported')
            ->with($config)
            ->willReturn(true);

        $this->inlineEditingConfigurator->expects(self::never())
            ->method('configureInlineEditingForGrid');
        $this->inlineEditingConfigurator->expects(self::never())
            ->method('configureInlineEditingForColumn');

        $this->extension->processConfigs($config);

        $actualParameters = $config->toArray();
        self::assertArrayHasKey('tags', $actualParameters['columns']);
        self::assertArrayHasKey('tagname', $actualParameters['filters']['columns']);
        self::assertFalse($actualParameters['columns']['tags']['inline_editing']['enable']);
    }
}
