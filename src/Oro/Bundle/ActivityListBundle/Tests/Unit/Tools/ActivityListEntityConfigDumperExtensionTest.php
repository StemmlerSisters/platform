<?php

namespace Oro\Bundle\ActivityListBundle\Tests\Unit\Tools;

use Oro\Bundle\ActivityListBundle\Entity\ActivityList;
use Oro\Bundle\ActivityListBundle\Provider\ActivityListChainProvider;
use Oro\Bundle\ActivityListBundle\Tools\ActivityListEntityConfigDumperExtension;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\Tools\AssociationBuilder;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ActivityListEntityConfigDumperExtensionTest extends TestCase
{
    private ActivityListChainProvider&MockObject $listProvider;
    private ConfigManager&MockObject $configManager;
    private AssociationBuilder&MockObject $associationBuilder;
    private ActivityListEntityConfigDumperExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->listProvider = $this->createMock(ActivityListChainProvider::class);
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->associationBuilder = $this->createMock(AssociationBuilder::class);

        $this->extension = new ActivityListEntityConfigDumperExtension(
            $this->listProvider,
            $this->configManager,
            $this->associationBuilder
        );
    }

    public function testBadActionSupports(): void
    {
        $this->assertFalse($this->extension->supports(ExtendConfigDumper::ACTION_POST_UPDATE));
    }

    public function testEmptyTargetsSupports(): void
    {
        $provider = $this->createMock(ConfigProvider::class);
        $provider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([]);

        $this->configManager->expects($this->once())
            ->method('getProvider')
            ->willReturn($provider);

        $this->assertFalse($this->extension->supports(ExtendConfigDumper::ACTION_PRE_UPDATE));
    }

    public function testSupports(): void
    {
        $configId = new EntityConfigId('extend', 'Acme\TestBundle\Entity\TestEntity');
        $config = new Config($configId);
        $config->set('upgradeable', true);

        $provider = $this->createMock(ConfigProvider::class);
        $provider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->configManager->expects($this->any())
            ->method('getProvider')
            ->willReturn($provider);

        $this->listProvider->expects($this->once())
            ->method('getTargetEntityClasses')
            ->willReturn(['Acme\TestBundle\Entity\TestEntity']);

        $provider->expects($this->once())
            ->method('hasConfig')
            ->with(ActivityListEntityConfigDumperExtension::ENTITY_CLASS)
            ->willReturn(true);

        $this->assertTrue($this->extension->supports(ExtendConfigDumper::ACTION_PRE_UPDATE));
    }

    public function testPreUpdate(): void
    {
        $configId = new EntityConfigId('extend', 'Acme\TestBundle\Entity\TestEntity');
        $config = new Config($configId);
        $config->set('upgradeable', true);

        $provider = $this->createMock(ConfigProvider::class);
        $provider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->listProvider->expects($this->any())
            ->method('getTargetEntityClasses')
            ->willReturn(['Acme\TestBundle\Entity\TestEntity']);

        $this->configManager->expects($this->any())
            ->method('getProvider')
            ->willReturn($provider);

        $this->associationBuilder->expects($this->once())
            ->method('createManyToManyAssociation')
            ->with(
                ActivityList::class,
                'Acme\TestBundle\Entity\TestEntity',
                'activityList'
            );

        $this->extension->preUpdate();
    }
}
