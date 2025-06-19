<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Tools;

use Oro\Bundle\AttachmentBundle\Entity\Attachment;
use Oro\Bundle\AttachmentBundle\Tools\AttachmentAssociationHelper;
use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AttachmentAssociationHelperTest extends TestCase
{
    private ConfigManager&MockObject $configManager;
    private AttachmentAssociationHelper $attachmentAssociationHelper;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);

        $this->attachmentAssociationHelper = new AttachmentAssociationHelper($this->configManager);
    }

    public function testIsAttachmentAssociationEnabledForNotConfigurableEntity(): void
    {
        $entityClass = 'Test\Entity';

        $this->configManager->expects($this->once())
            ->method('hasConfig')
            ->with($entityClass)
            ->willReturn(false);
        $this->configManager->expects($this->never())
            ->method('getEntityConfig');

        $this->assertFalse(
            $this->attachmentAssociationHelper->isAttachmentAssociationEnabled($entityClass)
        );
    }

    public function testIsAttachmentAssociationEnabledForDisabledAssociation(): void
    {
        $entityClass = 'Test\Entity';

        $config = new Config(new EntityConfigId('attachment', $entityClass));

        $this->configManager->expects($this->once())
            ->method('hasConfig')
            ->with($entityClass)
            ->willReturn(true);
        $this->configManager->expects($this->once())
            ->method('getEntityConfig')
            ->with('attachment', $entityClass)
            ->willReturn($config);

        $this->assertFalse(
            $this->attachmentAssociationHelper->isAttachmentAssociationEnabled($entityClass)
        );
    }

    public function testIsAttachmentAssociationEnabledForEnabledAssociation(): void
    {
        $entityClass = 'Test\Entity';

        $config = new Config(new EntityConfigId('attachment', $entityClass));
        $config->set('enabled', true);

        $associationName = ExtendHelper::buildAssociationName($entityClass);
        $associationConfig = new Config(
            new FieldConfigId('extend', Attachment::class, $associationName)
        );
        $associationConfig->set('is_extend', true);
        $associationConfig->set('state', ExtendScope::STATE_ACTIVE);

        $this->configManager->expects($this->exactly(2))
            ->method('hasConfig')
            ->willReturnMap([
                [$entityClass, null, true],
                [Attachment::class, $associationName, true],
            ]);
        $this->configManager->expects($this->once())
            ->method('getEntityConfig')
            ->with('attachment', $entityClass)
            ->willReturn($config);
        $this->configManager->expects($this->once())
            ->method('getFieldConfig')
            ->with('extend', Attachment::class, $associationName)
            ->willReturn($associationConfig);

        $this->assertTrue(
            $this->attachmentAssociationHelper->isAttachmentAssociationEnabled($entityClass)
        );
    }

    public function testIsAttachmentAssociationEnabledForEnabledButNotAccessibleAssociation(): void
    {
        $entityClass = 'Test\Entity';

        $config = new Config(new EntityConfigId('attachment', $entityClass));
        $config->set('enabled', true);

        $associationName = ExtendHelper::buildAssociationName($entityClass);
        $associationConfig = new Config(
            new FieldConfigId('extend', Attachment::class, $associationName)
        );
        $associationConfig->set('is_extend', true);
        $associationConfig->set('state', ExtendScope::STATE_NEW);

        $this->configManager->expects($this->exactly(2))
            ->method('hasConfig')
            ->willReturnMap([
                [$entityClass, null, true],
                [Attachment::class, $associationName, true],
            ]);
        $this->configManager->expects($this->once())
            ->method('getEntityConfig')
            ->with('attachment', $entityClass)
            ->willReturn($config);
        $this->configManager->expects($this->once())
            ->method('getFieldConfig')
            ->with('extend', Attachment::class, $associationName)
            ->willReturn($associationConfig);

        $this->assertFalse(
            $this->attachmentAssociationHelper->isAttachmentAssociationEnabled($entityClass)
        );
    }

    public function testIsAttachmentAssocEnabledForEnabledButNotAccessibleAssociationButWithAccessibleFalse(): void
    {
        $entityClass = 'Test\Entity';

        $config = new Config(new EntityConfigId('attachment', $entityClass));
        $config->set('enabled', true);

        $this->configManager->expects($this->once())
            ->method('hasConfig')
            ->with($entityClass)
            ->willReturn(true);
        $this->configManager->expects($this->once())
            ->method('getEntityConfig')
            ->with('attachment', $entityClass)
            ->willReturn($config);
        $this->configManager->expects($this->never())
            ->method('getFieldConfig');

        $this->assertTrue(
            $this->attachmentAssociationHelper->isAttachmentAssociationEnabled($entityClass, false)
        );
    }
}
