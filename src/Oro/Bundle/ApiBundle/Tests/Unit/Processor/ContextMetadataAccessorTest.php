<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Processor\Context;
use Oro\Bundle\ApiBundle\Processor\ContextMetadataAccessor;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Contact;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Group;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\Product;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\User;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\UserProfile;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ContextMetadataAccessorTest extends TestCase
{
    private Context&MockObject $context;
    private ContextMetadataAccessor $metadataAccessor;

    #[\Override]
    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);

        $this->metadataAccessor = new ContextMetadataAccessor($this->context);
    }

    public function testGetMetadataForContextClass(): void
    {
        $className = User::class;
        $config = new EntityDefinitionConfig();
        $metadata = new EntityMetadata($className);

        $this->context->expects(self::once())
            ->method('getClassName')
            ->willReturn($className);
        $this->context->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);
        $this->context->expects(self::once())
            ->method('getMetadata')
            ->willReturn($metadata);

        self::assertSame($metadata, $this->metadataAccessor->getMetadata($className));
    }

    public function testGetMetadataForContextClassForCaseWhenApiResourceIsBasedOnManageableEntity(): void
    {
        $className = User::class;
        $config = new EntityDefinitionConfig();
        $metadata = new EntityMetadata($className);

        $this->context->expects(self::once())
            ->method('getClassName')
            ->willReturn(UserProfile::class);
        $this->context->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);
        $this->context->expects(self::once())
            ->method('getMetadata')
            ->willReturn($metadata);

        self::assertSame($metadata, $this->metadataAccessor->getMetadata($className));
    }

    public function testGetMetadataForNotContextClass(): void
    {
        $config = new EntityDefinitionConfig();

        $this->context->expects(self::once())
            ->method('getClassName')
            ->willReturn(User::class);
        $this->context->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);
        $this->context->expects(self::never())
            ->method('getMetadata');

        self::assertNull($this->metadataAccessor->getMetadata(Product::class));
    }

    public function testGetMetadataForFormDataClass(): void
    {
        $className = User::class;
        $formDataClass = Contact::class;
        $config = new EntityDefinitionConfig();
        $config->setFormOption('data_class', $formDataClass);
        $metadata = new EntityMetadata($className);

        $this->context->expects(self::exactly(2))
            ->method('getClassName')
            ->willReturn($className);
        $this->context->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);
        $this->context->expects(self::once())
            ->method('getMetadata')
            ->willReturn($metadata);

        self::assertSame($metadata, $this->metadataAccessor->getMetadata($formDataClass));
    }

    public function testGetMetadataForNotFormDataClass(): void
    {
        $className = User::class;
        $formDataClass = Contact::class;
        $config = new EntityDefinitionConfig();
        $config->setFormOption('data_class', $formDataClass);

        $this->context->expects(self::once())
            ->method('getClassName')
            ->willReturn($className);
        $this->context->expects(self::once())
            ->method('getConfig')
            ->willReturn($config);
        $this->context->expects(self::never())
            ->method('getMetadata');

        self::assertNull($this->metadataAccessor->getMetadata(Group::class));
    }
}
