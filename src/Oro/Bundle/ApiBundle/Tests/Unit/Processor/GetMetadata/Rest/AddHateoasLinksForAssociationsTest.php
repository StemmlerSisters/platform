<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetMetadata\Rest;

use Oro\Bundle\ApiBundle\Metadata\AssociationMetadata;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Metadata\ExternalLinkMetadata;
use Oro\Bundle\ApiBundle\Metadata\RouteLinkMetadata;
use Oro\Bundle\ApiBundle\Processor\GetMetadata\Rest\AddHateoasLinksForAssociations;
use Oro\Bundle\ApiBundle\Provider\SubresourcesProvider;
use Oro\Bundle\ApiBundle\Request\ApiAction;
use Oro\Bundle\ApiBundle\Request\ApiResourceSubresources;
use Oro\Bundle\ApiBundle\Request\Rest\RestRoutes;
use Oro\Bundle\ApiBundle\Request\Rest\RestRoutesRegistry;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetMetadata\MetadataProcessorTestCase;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use Oro\Component\Testing\Unit\TestContainerBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AddHateoasLinksForAssociationsTest extends MetadataProcessorTestCase
{
    private SubresourcesProvider&MockObject $subresourcesProvider;
    private AddHateoasLinksForAssociations $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $routes = new RestRoutes('item', 'list', 'subresource', 'relationship');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->subresourcesProvider = $this->createMock(SubresourcesProvider::class);
        $this->processor = new AddHateoasLinksForAssociations(
            new RestRoutesRegistry(
                [['routes', 'rest']],
                TestContainerBuilder::create()->add('routes', $routes)->getContainer($this),
                new RequestExpressionMatcher()
            ),
            $urlGenerator,
            $this->subresourcesProvider
        );
    }

    public function testProcessWhenNotEntityMetadataInResult(): void
    {
        $this->processor->process($this->context);
        self::assertFalse($this->context->hasResult());
    }

    public function testProcessWhenLinksAlreadyExistsInAssociationMetadata(): void
    {
        $existingSelfLinkMetadata = new ExternalLinkMetadata('url1');
        $existingRelatedLinkMetadata = new ExternalLinkMetadata('url2');
        $entityMetadata = new EntityMetadata($this->context->getClassName());
        $associationMetadata = $entityMetadata->addAssociation(new AssociationMetadata('association1'));
        $associationMetadata->addRelationshipLink('self', $existingSelfLinkMetadata);
        $associationMetadata->addRelationshipLink('related', $existingRelatedLinkMetadata);

        $subresources = new ApiResourceSubresources($this->context->getClassName());
        $subresources->addSubresource($associationMetadata->getName());

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresources')
            ->with($this->context->getClassName(), $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn($subresources);

        $this->context->setResult($entityMetadata);
        $this->processor->process($this->context);

        self::assertCount(2, $associationMetadata->getRelationshipLinks());
        self::assertSame($existingSelfLinkMetadata, $associationMetadata->getRelationshipLink('self'));
        self::assertSame($existingRelatedLinkMetadata, $associationMetadata->getRelationshipLink('related'));
    }

    public function testProcessWhenLinksDoNotExistInAssociationMetadata(): void
    {
        $entityMetadata = new EntityMetadata($this->context->getClassName());
        $associationMetadata = $entityMetadata->addAssociation(new AssociationMetadata('association1'));

        $subresources = new ApiResourceSubresources($this->context->getClassName());
        $subresources->addSubresource($associationMetadata->getName());

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresources')
            ->with($this->context->getClassName(), $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn($subresources);

        $this->context->setResult($entityMetadata);
        $this->processor->process($this->context);

        self::assertCount(2, $associationMetadata->getRelationshipLinks());
        /** @var RouteLinkMetadata $selfLinkMetadata */
        $selfLinkMetadata = $associationMetadata->getRelationshipLink('self');
        self::assertInstanceOf(RouteLinkMetadata::class, $selfLinkMetadata);
        self::assertEquals(
            [
                'route_name'     => 'relationship',
                'route_params'   => ['entity' => '_.__type__', 'id' => '_.__id__'],
                'default_params' => ['association' => $associationMetadata->getName()]
            ],
            $selfLinkMetadata->toArray()
        );
        /** @var RouteLinkMetadata $relatedLinkMetadata */
        $relatedLinkMetadata = $associationMetadata->getRelationshipLink('related');
        self::assertInstanceOf(RouteLinkMetadata::class, $relatedLinkMetadata);
        self::assertEquals(
            [
                'route_name'     => 'subresource',
                'route_params'   => ['entity' => '_.__type__', 'id' => '_.__id__'],
                'default_params' => ['association' => $associationMetadata->getName()]
            ],
            $relatedLinkMetadata->toArray()
        );
    }

    public function testProcessWhenGetSubresourceActionIsDisabledForAssociation(): void
    {
        $entityMetadata = new EntityMetadata($this->context->getClassName());
        $associationMetadata = $entityMetadata->addAssociation(new AssociationMetadata('association1'));

        $subresources = new ApiResourceSubresources($this->context->getClassName());
        $subresources->addSubresource($associationMetadata->getName())
            ->addExcludedAction(ApiAction::GET_SUBRESOURCE);

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresources')
            ->with($this->context->getClassName(), $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn($subresources);

        $this->context->setResult($entityMetadata);
        $this->processor->process($this->context);

        self::assertCount(1, $associationMetadata->getRelationshipLinks());
        /** @var RouteLinkMetadata $selfLinkMetadata */
        $selfLinkMetadata = $associationMetadata->getRelationshipLink('self');
        self::assertInstanceOf(RouteLinkMetadata::class, $selfLinkMetadata);
        self::assertEquals(
            [
                'route_name'     => 'relationship',
                'route_params'   => ['entity' => '_.__type__', 'id' => '_.__id__'],
                'default_params' => ['association' => $associationMetadata->getName()]
            ],
            $selfLinkMetadata->toArray()
        );
    }

    public function testProcessWhenGetRelationshipActionIsDisabledForAssociation(): void
    {
        $entityMetadata = new EntityMetadata($this->context->getClassName());
        $associationMetadata = $entityMetadata->addAssociation(new AssociationMetadata('association1'));

        $subresources = new ApiResourceSubresources($this->context->getClassName());
        $subresources->addSubresource($associationMetadata->getName())
            ->addExcludedAction(ApiAction::GET_RELATIONSHIP);

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresources')
            ->with($this->context->getClassName(), $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn($subresources);

        $this->context->setResult($entityMetadata);
        $this->processor->process($this->context);

        self::assertCount(1, $associationMetadata->getRelationshipLinks());
        /** @var RouteLinkMetadata $relatedLinkMetadata */
        $relatedLinkMetadata = $associationMetadata->getRelationshipLink('related');
        self::assertInstanceOf(RouteLinkMetadata::class, $relatedLinkMetadata);
        self::assertEquals(
            [
                'route_name'     => 'subresource',
                'route_params'   => ['entity' => '_.__type__', 'id' => '_.__id__'],
                'default_params' => ['association' => $associationMetadata->getName()]
            ],
            $relatedLinkMetadata->toArray()
        );
    }

    public function testProcessWhenAssociationDoesNotHaveSubresources(): void
    {
        $entityMetadata = new EntityMetadata($this->context->getClassName());
        $associationMetadata = $entityMetadata->addAssociation(new AssociationMetadata('association1'));

        $subresources = new ApiResourceSubresources($this->context->getClassName());

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresources')
            ->with($this->context->getClassName(), $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn($subresources);

        $this->context->setResult($entityMetadata);
        $this->processor->process($this->context);

        self::assertCount(0, $associationMetadata->getRelationshipLinks());
    }

    public function testProcessWhenEntityDoesNotHaveSubresources(): void
    {
        $entityMetadata = new EntityMetadata($this->context->getClassName());
        $associationMetadata = $entityMetadata->addAssociation(new AssociationMetadata('association1'));

        $this->subresourcesProvider->expects(self::once())
            ->method('getSubresources')
            ->with($this->context->getClassName(), $this->context->getVersion(), $this->context->getRequestType())
            ->willReturn(null);

        $this->context->setResult($entityMetadata);
        $this->processor->process($this->context);

        self::assertCount(0, $associationMetadata->getRelationshipLinks());
    }
}
