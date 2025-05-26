<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Provider;

use Oro\Bundle\EmailBundle\Provider\EntityRouteVariablesProvider;
use Oro\Bundle\EmailBundle\Tests\Unit\Fixtures\Entity\TestEntityForVariableProvider;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Metadata\EntityMetadata;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class EntityRouteVariablesProviderTest extends TestCase
{
    private const string TEST_ENTITY_NAME = TestEntityForVariableProvider::class;
    private const string TEST_EXTEND_ENTITY_NAME = ExtendHelper::ENTITY_NAMESPACE . 'TestEntity';

    private EntityRouteVariablesProvider $provider;
    private ConfigManager&MockObject $configManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->any())
            ->method('trans')
            ->willReturnArgument(0);

        $this->provider = new EntityRouteVariablesProvider(
            $translator,
            $this->configManager
        );
    }

    public function testGetVariableDefinitions(): void
    {
        $entityData = [
            'url.index' => [
                'type'  => 'string',
                'label' => 'oro.email.emailtemplate.variables.url.index.label'
            ],
            'url.view'  => [
                'type'  => 'string',
                'label' => 'oro.email.emailtemplate.variables.url.view.label'
            ]
        ];
        $extendEntityData = $entityData;
        $expected = [
            self::TEST_ENTITY_NAME        => $entityData,
            self::TEST_EXTEND_ENTITY_NAME => $extendEntityData
        ];

        $entityMetadata = null;
        $config = new Config(
            new EntityConfigId('extend', self::TEST_ENTITY_NAME),
            ['is_extend' => true]
        );
        $this->configManager->expects($this->atLeastOnce())
            ->method('hasConfig')
            ->willReturn(true);
        $this->configManager->expects($this->atLeastOnce())
            ->method('getEntityConfig')
            ->willReturn($config);

        $this->configManager->expects($this->atLeastOnce())
            ->method('getEntityMetadata')
            ->willReturnCallback(function ($class) {
                $metadata = null;
                if (!ExtendHelper::isCustomEntity($class)) {
                    $metadata = new EntityMetadata(self::TEST_ENTITY_NAME);
                    $metadata->routeName = 'oro_test_index';
                    $metadata->routeView = 'oro_test_view';
                }

                return $metadata;
            });

        $this->configManager->expects($this->once())
            ->method('getIds')
            ->with('entity')
            ->willReturn([
                new EntityConfigId('entity', self::TEST_ENTITY_NAME),
                new EntityConfigId('entity', self::TEST_EXTEND_ENTITY_NAME)
            ]);

        $this->assertEquals($expected, $this->provider->getVariableDefinitions());
    }

    public function testGetVariableGetters(): void
    {
        $this->assertEquals([], $this->provider->getVariableGetters());
    }

    /**
     * @dataProvider variableProcessorsDataProvider
     */
    public function testGetVariableProcessors(string $entityClass, array $expected): void
    {
        $config = new Config(
            new EntityConfigId('extend', self::TEST_ENTITY_NAME),
            ['is_extend' => true]
        );
        $this->configManager->expects($this->atLeastOnce())
            ->method('hasConfig')
            ->willReturn(true);
        $this->configManager->expects($this->atLeastOnce())
            ->method('getEntityConfig')
            ->willReturn($config);

        $this->configManager->expects($this->atLeastOnce())
            ->method('getEntityMetadata')
            ->willReturnCallback(function ($class) {
                $metadata = null;
                if (!ExtendHelper::isCustomEntity($class)) {
                    $metadata = new EntityMetadata(self::TEST_ENTITY_NAME);
                    $metadata->routeName = 'oro_test_index';
                    $metadata->routeView = 'oro_test_view';
                }

                return $metadata;
            });

        $this->assertEquals($expected, $this->provider->getVariableProcessors($entityClass));
    }

    public function variableProcessorsDataProvider(): array
    {
        $entityData = [
            'url.index' => [
                'processor' => 'entity_routes',
                'route'     => 'oro_test_index'
            ],
            'url.view'  => [
                'processor' => 'entity_routes',
                'route'     => 'oro_test_view'
            ]
        ];
        $extendEntityData = $entityData;
        $extendEntityData['url.index']['route'] = 'oro_entity_index';
        $extendEntityData['url.view']['route'] = 'oro_entity_view';

        return [
            'general entity class' => [
                'entityClass' => self::TEST_ENTITY_NAME,
                'expected'    => $entityData
            ],
            'custom entity class'  => [
                'entityClass' => self::TEST_EXTEND_ENTITY_NAME,
                'expected'    => $extendEntityData
            ]
        ];
    }
}
