<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\UpdateList;

use Oro\Bundle\ApiBundle\Entity\AsyncOperation;
use Oro\Bundle\ApiBundle\Processor\UpdateList\SetContentLocationHeader;
use Oro\Bundle\ApiBundle\Request\DataType;
use Oro\Bundle\ApiBundle\Request\Rest\RestRoutes;
use Oro\Bundle\ApiBundle\Request\Rest\RestRoutesRegistry;
use Oro\Bundle\ApiBundle\Request\ValueNormalizer;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use Oro\Component\Testing\Unit\TestContainerBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class SetContentLocationHeaderTest extends UpdateListProcessorTestCase
{
    private const string ITEM_ROUTE_NAME = 'item_route';

    private RouterInterface&MockObject $router;
    private ValueNormalizer&MockObject $valueNormalizer;
    private SetContentLocationHeader $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->router = $this->createMock(RouterInterface::class);
        $this->valueNormalizer = $this->createMock(ValueNormalizer::class);

        $routes = $this->createMock(RestRoutes::class);
        $routes->expects(self::any())
            ->method('getItemRouteName')
            ->willReturn(self::ITEM_ROUTE_NAME);

        $this->processor = new SetContentLocationHeader(
            new RestRoutesRegistry(
                [['routes', 'rest']],
                TestContainerBuilder::create()->add('routes', $routes)->getContainer($this),
                new RequestExpressionMatcher()
            ),
            $this->router,
            $this->valueNormalizer
        );
    }

    public function testProcessForSynchronousModeAndWithResponseData(): void
    {
        $this->context->setSynchronousMode(true);
        $this->context->setResult([]);
        $this->processor->process($this->context);

        self::assertFalse(
            $this->context->getResponseHeaders()->has(SetContentLocationHeader::RESPONSE_HEADER_NAME)
        );
    }

    public function testProcessForSynchronousModeAndWithoutResponseData(): void
    {
        $this->context->setSynchronousMode(true);
        $this->processor->process($this->context);

        $location = 'test location';

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with(AsyncOperation::class, DataType::ENTITY_TYPE, $this->context->getRequestType())
            ->willReturn('test_entity');
        $this->router->expects(self::once())
            ->method('generate')
            ->willReturn($location);

        $this->context->setOperationId(123);
        $this->processor->process($this->context);

        self::assertEquals(
            $location,
            $this->context->getResponseHeaders()->get(SetContentLocationHeader::RESPONSE_HEADER_NAME)
        );
    }

    public function testProcessWithExistingHeader(): void
    {
        $existingLocation = 'existing location';

        $this->context->getResponseHeaders()->set(SetContentLocationHeader::RESPONSE_HEADER_NAME, $existingLocation);
        $this->processor->process($this->context);

        self::assertEquals(
            $existingLocation,
            $this->context->getResponseHeaders()->get(SetContentLocationHeader::RESPONSE_HEADER_NAME)
        );
    }

    public function testProcessWhenNoOperationId(): void
    {
        $this->valueNormalizer->expects(self::never())
            ->method('normalizeValue');
        $this->router->expects(self::never())
            ->method('generate');

        $this->processor->process($this->context);

        self::assertFalse(
            $this->context->getResponseHeaders()->has(SetContentLocationHeader::RESPONSE_HEADER_NAME)
        );
    }

    public function testProcess(): void
    {
        $location = 'test location';
        $entityType = 'test_entity';
        $operationId = 123;

        $this->valueNormalizer->expects(self::once())
            ->method('normalizeValue')
            ->with(AsyncOperation::class, DataType::ENTITY_TYPE, $this->context->getRequestType())
            ->willReturn($entityType);
        $this->router->expects(self::once())
            ->method('generate')
            ->with(
                self::ITEM_ROUTE_NAME,
                ['entity' => $entityType, 'id' => (string)$operationId],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn($location);

        $this->context->setOperationId($operationId);
        $this->processor->process($this->context);

        self::assertEquals(
            $location,
            $this->context->getResponseHeaders()->get(SetContentLocationHeader::RESPONSE_HEADER_NAME)
        );
    }
}
