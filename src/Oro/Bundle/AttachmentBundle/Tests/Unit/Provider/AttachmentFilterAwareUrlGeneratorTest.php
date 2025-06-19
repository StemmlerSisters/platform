<?php

namespace Oro\Bundle\AttachmentBundle\Tests\Unit\Provider;

use Oro\Bundle\AttachmentBundle\Configurator\Provider\AttachmentHashProvider;
use Oro\Bundle\AttachmentBundle\Provider\AttachmentFilterAwareUrlGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class AttachmentFilterAwareUrlGeneratorTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private AttachmentHashProvider&MockObject $attachmentHashProvider;
    private LoggerInterface&MockObject $logger;
    private AttachmentFilterAwareUrlGenerator $filterAwareGenerator;

    #[\Override]
    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->attachmentHashProvider = $this->createMock(AttachmentHashProvider::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->filterAwareGenerator = new AttachmentFilterAwareUrlGenerator(
            $this->urlGenerator,
            $this->attachmentHashProvider,
            $this->logger
        );
    }

    public function testSetContext(): void
    {
        $context = $this->createMock(RequestContext::class);
        $this->urlGenerator->expects(self::once())
            ->method('setContext')
            ->with($context);

        $this->filterAwareGenerator->setContext($context);
    }

    public function testGetContext(): void
    {
        $context = $this->createMock(RequestContext::class);
        $this->urlGenerator->expects(self::once())
            ->method('getContext')
            ->willReturn($context);

        self::assertSame($context, $this->filterAwareGenerator->getContext());
    }

    public function testGenerateWithoutFilter(): void
    {
        $route = 'test';
        $parameters = ['id' => 1];

        $this->attachmentHashProvider->expects(self::never())
            ->method(self::anything());

        $this->urlGenerator->expects(self::once())
            ->method('generate')
            ->with($route, $parameters)
            ->willReturn('/test/1');
        self::assertSame('/test/1', $this->filterAwareGenerator->generate($route, $parameters));
    }

    public function testGenerateWithFilter(): void
    {
        $route = 'test';
        $parameters = ['id' => 1, 'filter' => 'test_filter', 'filename' => 'image.jpg'];

        $filterHash = md5(json_encode(['size' => ['height' => 'auto']], JSON_THROW_ON_ERROR));

        $this->attachmentHashProvider->expects(self::once())
            ->method('getFilterConfigHash')
            ->with($parameters['filter'], 'jpg')
            ->willReturn($filterHash);

        $this->urlGenerator->expects(self::once())
            ->method('generate')
            ->with(
                $route,
                [
                    'id' => 1,
                    'filter' => 'test_filter',
                    'filterMd5' => $filterHash,
                    'filename' => 'image.jpg',
                ]
            )
            ->willReturn('/test/1');
        self::assertSame('/test/1', $this->filterAwareGenerator->generate($route, $parameters));
    }

    public function testGenerateWithFilterAndFormat(): void
    {
        $route = 'test';
        $parameters = ['id' => 1, 'filter' => 'test_filter', 'format' => 'test_format'];

        $filterHash = md5(json_encode(['size' => ['height' => 'auto']], JSON_THROW_ON_ERROR));

        $this->attachmentHashProvider->expects(self::once())
            ->method('getFilterConfigHash')
            ->with($parameters['filter'], $parameters['format'])
            ->willReturn($filterHash);

        $this->urlGenerator->expects(self::once())
            ->method('generate')
            ->with($route, ['id' => 1, 'filter' => 'test_filter', 'filterMd5' => $filterHash])
            ->willReturn('/test/1');
        self::assertSame('/test/1', $this->filterAwareGenerator->generate($route, $parameters));
    }

    public function testGenerateWhenException(): void
    {
        $route = 'test';
        $parameters = ['id' => 1];
        $exception = new InvalidParameterException();

        $this->attachmentHashProvider->expects(self::never())
            ->method(self::anything());

        $this->urlGenerator->expects(self::once())
            ->method('generate')
            ->with($route, $parameters)
            ->willThrowException($exception);

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to generate file url.',
                ['route' => $route, 'routeParameters' => $parameters, 'exception' => $exception]
            );

        self::assertEquals('', $this->filterAwareGenerator->generate($route, $parameters));
    }

    public function testGenerateWhenGeneratorReturnsEmptyString(): void
    {
        $route = 'test';
        $parameters = ['id' => 1];

        $this->attachmentHashProvider->expects(self::never())
            ->method(self::anything());

        $this->urlGenerator->expects(self::once())
            ->method('generate')
            ->with($route, $parameters)
            ->willReturn('');

        $this->logger->expects(self::never())
            ->method(self::anything());

        self::assertEquals('', $this->filterAwareGenerator->generate($route, $parameters));
    }

    public function testGetFilterHash(): void
    {
        $filterName = 'filterName';
        $filterConfig = md5(json_encode(['filterConfig'], JSON_THROW_ON_ERROR));

        $this->attachmentHashProvider->expects(self::once())
            ->method('getFilterConfigHash')
            ->with($filterName, '')
            ->willReturn($filterConfig);

        self::assertEquals($filterConfig, $this->filterAwareGenerator->getFilterHash($filterName));
    }

    public function testGetFilterHashWithFormat(): void
    {
        $filterName = 'filterName';
        $format = 'sampleFormat';
        $filterConfig = md5(json_encode(['filterConfig'], JSON_THROW_ON_ERROR));

        $this->attachmentHashProvider->expects(self::once())
            ->method('getFilterConfigHash')
            ->with($filterName, $format)
            ->willReturn($filterConfig);

        self::assertEquals($filterConfig, $this->filterAwareGenerator->getFilterHash($filterName, $format));
    }
}
