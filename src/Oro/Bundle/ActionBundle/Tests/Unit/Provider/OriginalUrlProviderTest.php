<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Provider;

use Oro\Bundle\ActionBundle\Button\ButtonSearchContext;
use Oro\Bundle\ActionBundle\Provider\OriginalUrlProvider;
use Oro\Bundle\DataGridBundle\Converter\UrlConverter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class OriginalUrlProviderTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private UrlConverter&MockObject $datagridUrlConverter;
    private OriginalUrlProvider $urlProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->datagridUrlConverter = $this->createMock(UrlConverter::class);

        $this->urlProvider = new OriginalUrlProvider(
            $this->requestStack,
            $this->datagridUrlConverter
        );
    }

    public function testGetOriginalUrl(): void
    {
        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($this->getRequest('example.com'));

        self::assertEquals('example.com', $this->urlProvider->getOriginalUrl());
    }

    public function testGetOriginalUrlReturnNullIfRequestIsNotDefined(): void
    {
        self::assertNull($this->urlProvider->getOriginalUrl());
    }

    public function testGetOriginalUrlWhenDatagridIsSet(): void
    {
        $datagridName = 'quotes-grid';
        $pageParams = [
            'quotes-grid' =>
                [
                    'originalRoute' => 'oro_sale_quote_index',
                    '_pager' =>
                        [
                            '_page' => '1',
                            '_per_page' => '10',
                        ],
                    '_parameters' =>
                        [
                            'view' => '__all__',
                        ],
                    '_appearance' =>
                        [
                            '_type' => 'grid',
                        ],
                ],
            'appearanceType' => 'grid',
        ];

        $requestUri = '/admin/datagrid/quotes-grid?' . http_build_query($pageParams);
        $responseUri = '/admin/sale/quote?' . http_build_query([$datagridName => $pageParams[$datagridName]]);

        $this->requestStack->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($this->getRequest($requestUri));

        $this->datagridUrlConverter->expects(self::once())
            ->method('convertGridUrlToPageUrl')
            ->with($datagridName, $requestUri)
            ->willReturn($responseUri);

        $buttonContext = $this->getSearchButtonContext($datagridName);

        self::assertEquals(
            $responseUri,
            $this->urlProvider->getOriginalUrl($buttonContext)
        );
    }

    private function getRequest(string $requestUri): Request
    {
        return new Request([], [], [], [], [], ['REQUEST_URI' => $requestUri]);
    }

    private function getSearchButtonContext(?string $datagridName): ButtonSearchContext
    {
        $btnContext = new ButtonSearchContext();
        $btnContext->setDatagrid($datagridName);

        return $btnContext;
    }
}
