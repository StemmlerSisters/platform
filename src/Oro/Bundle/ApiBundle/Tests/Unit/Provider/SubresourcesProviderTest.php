<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Provider;

use Oro\Bundle\ApiBundle\Processor\CollectSubresources\CollectSubresourcesContext;
use Oro\Bundle\ApiBundle\Processor\CollectSubresourcesProcessor;
use Oro\Bundle\ApiBundle\Provider\ResourcesCache;
use Oro\Bundle\ApiBundle\Provider\ResourcesProvider;
use Oro\Bundle\ApiBundle\Provider\SubresourcesProvider;
use Oro\Bundle\ApiBundle\Request\ApiResource;
use Oro\Bundle\ApiBundle\Request\ApiResourceSubresources;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Component\ChainProcessor\ProcessorBagInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubresourcesProviderTest extends TestCase
{
    private CollectSubresourcesProcessor&MockObject $processor;
    private ResourcesProvider&MockObject $resourcesProvider;
    private ResourcesCache&MockObject $resourcesCache;
    private SubresourcesProvider $subresourcesProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->processor = $this->getMockBuilder(CollectSubresourcesProcessor::class)
            ->onlyMethods(['process'])
            ->setConstructorArgs([$this->createMock(ProcessorBagInterface::class), 'collect_subresources'])
            ->getMock();
        $this->resourcesProvider = $this->createMock(ResourcesProvider::class);
        $this->resourcesCache = $this->createMock(ResourcesCache::class);

        $this->subresourcesProvider = new SubresourcesProvider(
            $this->processor,
            $this->resourcesProvider,
            $this->resourcesCache
        );
    }

    public function testGetSubresourcesNoCache(): void
    {
        $entityClass = 'Test\Entity';
        $version = '1.2.3';
        $requestType = new RequestType([RequestType::REST, RequestType::JSON_API]);

        $resources = [
            new ApiResource('Test\Entity1'),
            new ApiResource('Test\Entity3')
        ];
        $accessibleResources = ['Test\Entity1'];
        $expectedSubresources = new ApiResourceSubresources($entityClass);
        $expectedSubresources->addSubresource('test');

        $this->processor->expects(self::once())
            ->method('process')
            ->willReturnCallback(
                function (CollectSubresourcesContext $context) use (
                    $version,
                    $requestType,
                    $accessibleResources
                ) {
                    self::assertEquals($version, $context->getVersion());
                    self::assertEquals($requestType, $context->getRequestType());
                    self::assertEquals(
                        [
                            'Test\Entity1' => new ApiResource('Test\Entity1'),
                            'Test\Entity3' => new ApiResource('Test\Entity3')
                        ],
                        $context->getResources()
                    );
                    self::assertEquals($accessibleResources, $context->getAccessibleResources());

                    $subresources1 = new ApiResourceSubresources('Test\Entity');
                    $subresources1->addSubresource('test');
                    $context->getResult()->add($subresources1);
                }
            );
        $this->resourcesProvider->expects(self::once())
            ->method('getResources')
            ->with($version, self::identicalTo($requestType))
            ->willReturn($resources);
        $this->resourcesProvider->expects(self::once())
            ->method('getAccessibleResources')
            ->with($version, self::identicalTo($requestType))
            ->willReturn($accessibleResources);
        $this->resourcesCache->expects(self::once())
            ->method('getSubresources')
            ->with($entityClass, $version, self::identicalTo($requestType))
            ->willReturn(null);
        $this->resourcesCache->expects(self::once())
            ->method('saveSubresources')
            ->with($version, self::identicalTo($requestType), [$expectedSubresources]);

        self::assertEquals(
            $expectedSubresources,
            $this->subresourcesProvider->getSubresources($entityClass, $version, $requestType)
        );
    }

    public function testGetSubresourcesForUnknownEntity(): void
    {
        $entityClass = 'Test\Entity1';
        $version = '1.2.3';
        $requestType = new RequestType([RequestType::REST, RequestType::JSON_API]);

        $resources = [
            new ApiResource('Test\Entity1'),
            new ApiResource('Test\Entity3')
        ];
        $accessibleResources = ['Test\Entity1'];
        $subresources = new ApiResourceSubresources('Test\Entity2');
        $subresources->addSubresource('test');

        $this->processor->expects(self::once())
            ->method('process')
            ->willReturnCallback(
                function (CollectSubresourcesContext $context) use (
                    $version,
                    $requestType,
                    $accessibleResources
                ) {
                    self::assertEquals($version, $context->getVersion());
                    self::assertEquals($requestType, $context->getRequestType());
                    self::assertEquals(
                        [
                            'Test\Entity1' => new ApiResource('Test\Entity1'),
                            'Test\Entity3' => new ApiResource('Test\Entity3')
                        ],
                        $context->getResources()
                    );
                    self::assertEquals($accessibleResources, $context->getAccessibleResources());

                    $subresources2 = new ApiResourceSubresources('Test\Entity2');
                    $subresources2->addSubresource('test');
                    $context->getResult()->add($subresources2);
                }
            );
        $this->resourcesProvider->expects(self::once())
            ->method('getResources')
            ->with($version, self::identicalTo($requestType))
            ->willReturn($resources);
        $this->resourcesProvider->expects(self::once())
            ->method('getAccessibleResources')
            ->with($version, self::identicalTo($requestType))
            ->willReturn($accessibleResources);
        $this->resourcesCache->expects(self::once())
            ->method('getSubresources')
            ->with($entityClass, $version, self::identicalTo($requestType))
            ->willReturn(null);
        $this->resourcesCache->expects(self::once())
            ->method('saveSubresources')
            ->with($version, self::identicalTo($requestType), [$subresources]);

        self::assertNull(
            $this->subresourcesProvider->getSubresources($entityClass, $version, $requestType)
        );
    }

    public function testGetSubresourcesFromCache(): void
    {
        $entityClass = 'Test\Entity';
        $version = '1.2.3';
        $requestType = new RequestType([RequestType::REST, RequestType::JSON_API]);

        $expectedSubresources = new ApiResourceSubresources($entityClass);
        $expectedSubresources->addSubresource('test');

        $this->processor->expects(self::never())
            ->method('process');
        $this->resourcesProvider->expects(self::never())
            ->method('getResources');
        $this->resourcesProvider->expects(self::never())
            ->method('getAccessibleResources');
        $this->resourcesCache->expects(self::once())
            ->method('getSubresources')
            ->with($entityClass, $version, self::identicalTo($requestType))
            ->willReturn($expectedSubresources);
        $this->resourcesCache->expects(self::never())
            ->method('saveSubresources');

        self::assertEquals(
            $expectedSubresources,
            $this->subresourcesProvider->getSubresources($entityClass, $version, $requestType)
        );
    }

    public function testGetSubresourceForKnownAssociation(): void
    {
        $entityClass = 'Test\Entity';
        $version = '1.2.3';
        $requestType = new RequestType([RequestType::REST, RequestType::JSON_API]);

        $expectedSubresources = new ApiResourceSubresources($entityClass);
        $expectedSubresource = $expectedSubresources->addSubresource('test');

        $this->resourcesCache->expects(self::once())
            ->method('getSubresources')
            ->with($entityClass, $version, self::identicalTo($requestType))
            ->willReturn($expectedSubresources);

        self::assertEquals(
            $expectedSubresource,
            $this->subresourcesProvider->getSubresource($entityClass, 'test', $version, $requestType)
        );
    }

    public function testGetSubresourceForUnknownAssociation(): void
    {
        $entityClass = 'Test\Entity';
        $version = '1.2.3';
        $requestType = new RequestType([RequestType::REST, RequestType::JSON_API]);

        $expectedSubresources = new ApiResourceSubresources($entityClass);

        $this->resourcesCache->expects(self::once())
            ->method('getSubresources')
            ->with($entityClass, $version, self::identicalTo($requestType))
            ->willReturn($expectedSubresources);

        self::assertNull(
            $this->subresourcesProvider->getSubresource($entityClass, 'test', $version, $requestType)
        );
    }

    public function testGetSubresourceForForUnknownEntity(): void
    {
        $entityClass = 'Test\Entity1';
        $version = '1.2.3';
        $requestType = new RequestType([RequestType::REST, RequestType::JSON_API]);

        $resources = [
            new ApiResource('Test\Entity1'),
            new ApiResource('Test\Entity3')
        ];
        $accessibleResources = ['Test\Entity1'];
        $subresources = new ApiResourceSubresources('Test\Entity2');
        $subresources->addSubresource('test');

        $this->processor->expects(self::once())
            ->method('process')
            ->willReturnCallback(
                function (CollectSubresourcesContext $context) use (
                    $version,
                    $requestType,
                    $accessibleResources
                ) {
                    self::assertEquals($version, $context->getVersion());
                    self::assertEquals($requestType, $context->getRequestType());
                    self::assertEquals(
                        [
                            'Test\Entity1' => new ApiResource('Test\Entity1'),
                            'Test\Entity3' => new ApiResource('Test\Entity3')
                        ],
                        $context->getResources()
                    );
                    self::assertEquals($accessibleResources, $context->getAccessibleResources());

                    $subresources2 = new ApiResourceSubresources('Test\Entity2');
                    $subresources2->addSubresource('test');
                    $context->getResult()->add($subresources2);
                }
            );
        $this->resourcesProvider->expects(self::once())
            ->method('getResources')
            ->with($version, self::identicalTo($requestType))
            ->willReturn($resources);
        $this->resourcesProvider->expects(self::once())
            ->method('getAccessibleResources')
            ->with($version, self::identicalTo($requestType))
            ->willReturn($accessibleResources);
        $this->resourcesCache->expects(self::once())
            ->method('getSubresources')
            ->with($entityClass, $version, self::identicalTo($requestType))
            ->willReturn(null);
        $this->resourcesCache->expects(self::once())
            ->method('saveSubresources')
            ->with($version, self::identicalTo($requestType), [$subresources]);

        self::assertNull(
            $this->subresourcesProvider->getSubresource($entityClass, 'test', $version, $requestType)
        );
    }
}
