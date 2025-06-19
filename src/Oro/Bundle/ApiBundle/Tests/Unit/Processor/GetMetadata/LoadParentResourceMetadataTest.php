<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetMetadata;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\LoadParentResourceMetadata;
use Oro\Bundle\ApiBundle\Provider\MetadataProvider;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use PHPUnit\Framework\MockObject\MockObject;

class LoadParentResourceMetadataTest extends MetadataProcessorTestCase
{
    private MetadataProvider&MockObject $metadataProvider;
    private DoctrineHelper&MockObject $doctrineHelper;
    private LoadParentResourceMetadata $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->metadataProvider = $this->createMock(MetadataProvider::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);

        $this->processor = new LoadParentResourceMetadata(
            $this->metadataProvider,
            $this->doctrineHelper
        );
    }

    public function testProcessForAlreadyLoadedMetadata(): void
    {
        $metadata = new EntityMetadata('Test\Entity');

        $this->metadataProvider->expects(self::never())
            ->method('getMetadata');
        $this->doctrineHelper->expects(self::never())
            ->method('isManageableEntityClass');

        $this->context->setResult($metadata);
        $this->processor->process($this->context);

        self::assertSame($metadata, $this->context->getResult());
    }

    public function testProcessWhenResourceIsNotBasedOnAnotherResource(): void
    {
        $entityClass = 'Test\Entity';
        $config = new EntityDefinitionConfig();

        $this->metadataProvider->expects(self::never())
            ->method('getMetadata');
        $this->doctrineHelper->expects(self::never())
            ->method('isManageableEntityClass');

        $this->context->setClassName($entityClass);
        $this->context->setConfig($config);
        $this->processor->process($this->context);

        self::assertFalse($this->context->hasResult());
    }

    public function testProcessWhenResourceIsBasedOnAnotherResource(): void
    {
        $entityClass = 'Test\Entity';
        $parentEntityClass = 'Test\ParentEntity';
        $config = new EntityDefinitionConfig();
        $config->setParentResourceClass($parentEntityClass);

        $expectedConfig = new EntityDefinitionConfig();
        $expectedConfig->setParentResourceClass(null);

        $parentMetadata = new EntityMetadata($parentEntityClass);
        $parentMetadata->setHasIdentifierGenerator(true);

        $expectedMetadata = new EntityMetadata($entityClass);
        $expectedMetadata->setHasIdentifierGenerator(true);

        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with($entityClass)
            ->willReturn(false);

        $this->metadataProvider->expects(self::once())
            ->method('getMetadata')
            ->with(
                $parentEntityClass,
                $this->context->getVersion(),
                self::identicalTo($this->context->getRequestType()),
                $expectedConfig,
                $this->context->getExtras()
            )
            ->willReturn($parentMetadata);

        $this->context->setClassName($entityClass);
        $this->context->setConfig($config);
        $this->processor->process($this->context);

        self::assertEquals($parentEntityClass, $config->getParentResourceClass());
        self::assertEquals($expectedMetadata, $this->context->getResult());
    }

    public function testProcessWhenResourceIsBasedOnAnotherResourceButEntityIsManageable(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'The class "Test\Entity" must not be a manageable entity because it is based on another API resource.'
            . ' Parent resource is "Test\ParentEntity".'
        );

        $entityClass = 'Test\Entity';
        $parentEntityClass = 'Test\ParentEntity';
        $config = new EntityDefinitionConfig();
        $config->setParentResourceClass($parentEntityClass);

        $this->doctrineHelper->expects(self::once())
            ->method('isManageableEntityClass')
            ->with($entityClass)
            ->willReturn(true);

        $this->context->setClassName($entityClass);
        $this->context->setConfig($config);
        $this->processor->process($this->context);
    }
}
