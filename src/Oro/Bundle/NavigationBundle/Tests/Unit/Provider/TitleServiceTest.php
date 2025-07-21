<?php

namespace Oro\Bundle\NavigationBundle\Tests\Unit\Provider;

use Knp\Menu\ItemInterface;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\NavigationBundle\Menu\BreadcrumbManagerInterface;
use Oro\Bundle\NavigationBundle\Provider\TitleService;
use Oro\Bundle\NavigationBundle\Provider\TitleTranslator;
use Oro\Bundle\NavigationBundle\Title\TitleReader\TitleReaderRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class TitleServiceTest extends TestCase
{
    private TitleReaderRegistry&MockObject $titleReaderRegistry;
    private TitleTranslator&MockObject $titleTranslator;
    private BreadcrumbManagerInterface&MockObject $breadcrumbManager;
    private ConfigManager&MockObject $userConfigManager;
    private TitleService $titleService;

    #[\Override]
    protected function setUp(): void
    {
        $this->titleReaderRegistry = $this->createMock(TitleReaderRegistry::class);
        $this->titleTranslator = $this->createMock(TitleTranslator::class);
        $this->userConfigManager = $this->createMock(ConfigManager::class);
        $this->breadcrumbManager = $this->createMock(BreadcrumbManagerInterface::class);

        $this->titleService = new TitleService(
            $this->titleReaderRegistry,
            $this->titleTranslator,
            $this->userConfigManager,
            $this->breadcrumbManager
        );
    }

    public function testRender(): void
    {
        $this->titleTranslator->expects($this->once())
            ->method('trans')
            ->with('PrefixSuffix', [])
            ->willReturn('PrefixSuffix');

        $result = $this->titleService->render([], null, 'Prefix', 'Suffix');

        $this->assertIsString($result);
    }

    public function testRenderStored(): void
    {
        $data = '{"template":"test template","short_template":"test short template","params":{"prm1":"val1"},'
            . '"prefix":"test prefix","suffix":"test suffix"}';

        $this->titleTranslator->expects($this->once())
            ->method('trans')
            ->with('test prefixtest templatetest suffix', ['prm1' => 'val1'])
            ->willReturn('translated template');

        $result = $this->titleService->render([], $data, null, null, true);

        $this->assertEquals('translated template', $result);
    }

    public function testRenderStoredForShortTemplate(): void
    {
        $data = '{"template":"test template","short_template":"test short template","params":{"prm1":"val1"},'
            . '"prefix":"test prefix","suffix":"test suffix"}';

        $this->titleTranslator->expects($this->once())
            ->method('trans')
            ->with('test short template', ['prm1' => 'val1'])
            ->willReturn('translated short template');

        $result = $this->titleService->render([], $data, null, null, true, true);

        $this->assertEquals('translated short template', $result);
    }

    public function testRenderStoredWithoutOptionalData(): void
    {
        $data = '{"template":"test template","short_template":"test short template","params":{"prm1":"val1"}}';

        $this->titleTranslator->expects($this->once())
            ->method('trans')
            ->with('test template', ['prm1' => 'val1'])
            ->willReturn('translated template');

        $result = $this->titleService->render([], $data, null, null, true);

        $this->assertEquals('translated template', $result);
    }

    public function testRenderStoredWithEmptyData(): void
    {
        $data = '{"template":null,"short_template":null,"params":[]}';

        $this->titleTranslator->expects($this->once())
            ->method('trans')
            ->with('', [])
            ->willReturn('');

        $result = $this->titleService->render([], $data, null, null, true);

        $this->assertEquals('', $result);
    }

    public function testRenderStoredInvalidData(): void
    {
        $data = 'invalid';

        $this->titleTranslator->expects($this->once())
            ->method('trans')
            ->with('Untitled', [])
            ->willReturn('translated Untitled');

        $result = $this->titleService->render([], $data, null, null, true);

        $this->assertEquals('translated Untitled', $result);
    }

    public function testRenderShort(): void
    {
        $shortTitle = 'short title';
        $this->titleTranslator->expects($this->once())
            ->method('trans')
            ->with($shortTitle, [])
            ->willReturn($shortTitle);
        $this->titleService->setShortTemplate($shortTitle);
        $result = $this->titleService->render([], null, 'Prefix', 'Suffix', true, true);
        $this->assertIsString($result);
        $this->assertEquals($result, $shortTitle);
    }

    public function testSettersAndGetters(): void
    {
        $testString = 'Test string';
        $testArray = ['test'];

        $this->assertInstanceOf(
            TitleService::class,
            $this->titleService->setSuffix($testString)
        );
        $this->assertInstanceOf(
            TitleService::class,
            $this->titleService->setPrefix($testString)
        );

        $this->titleService->setParams($testArray);
        $this->assertEquals($testArray, $this->titleService->getParams());

        $dataArray = [
            'titleTemplate' => 'titleTemplate',
            'titleShortTemplate' => 'titleShortTemplate',
            'prefix' => 'prefix',
            'suffix' => 'suffix',
            'params' => ['test_params']
        ];
        $this->titleService->setData($dataArray);

        $this->assertEquals($dataArray['titleTemplate'], $this->titleService->getTemplate());
        $this->assertEquals($dataArray['titleShortTemplate'], $this->titleService->getShortTemplate());
        $this->assertEquals($dataArray['params'], $this->titleService->getParams());
    }

    public function testSetParamsObjectWithoutToString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Object of type stdClass used for "foo" title param don\'t have __toString() method.'
        );

        $this->titleService->setParams(
            [
                'foo' => new \stdClass(),
                'bar' => 'valid_param_value'
            ]
        );
    }

    public function testLoadByRoute(): void
    {
        $route = 'test_route';
        $testTitle = 'Test Title';
        $parentLabel = 'Parent Label';
        $menuItem = $this->createMock(ItemInterface::class);
        $menuItem->expects($this->once())
            ->method('getExtra')
            ->willReturn(['parent_route']);
        $breadcrumbs = [
            [
                'label' => $parentLabel,
                'uri'   => '/bar/foo',
                'item'  => $menuItem
            ]
        ];

        $this->titleReaderRegistry->expects($this->once())
            ->method('getTitleByRoute')
            ->with($route)
            ->willReturn($testTitle);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbLabels')
            ->willReturn([$parentLabel]);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbs')
            ->willReturn($breadcrumbs);

        $this->userConfigManager->expects($this->exactly(4))
            ->method('get')
            ->willReturnMap([
                ['oro_navigation.breadcrumb_menu', false, false, null, 'application_menu'],
                ['oro_navigation.breadcrumb_menu', false, false, null, 'application_menu'],
                ['oro_navigation.title_suffix', false, false, null, 'Suffix'],
                ['oro_navigation.title_delimiter', false, false, null, '-'],
            ]);

        $this->titleService->setPrefix('-');
        $this->titleService->loadByRoute($route);

        $this->assertEquals($testTitle.' - '.$parentLabel.' - Suffix', $this->titleService->getTemplate());
        $this->assertEquals($testTitle, $this->titleService->getShortTemplate());
    }

    public function testLoadByRouteForNullRoute(): void
    {
        $this->titleReaderRegistry->expects($this->never())
            ->method('getTitleByRoute');
        $this->breadcrumbManager->expects($this->never())
            ->method('getBreadcrumbLabels');
        $this->breadcrumbManager->expects($this->never())
            ->method('getBreadcrumbs');
        $this->userConfigManager->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['oro_navigation.title_suffix', false, false, null, 'Suffix'],
                ['oro_navigation.title_delimiter', false, false, null, '-']
            ]);

        $this->titleService->loadByRoute(null);

        $this->assertSame('Suffix', $this->titleService->getTemplate());
        $this->assertSame('', $this->titleService->getShortTemplate());
    }

    public function testLoadByRouteWhenTitleDoesNotExist(): void
    {
        $route = 'test_route';
        $parentLabel = 'Parent Label';
        $menuItem = $this->createMock(ItemInterface::class);
        $menuItem->expects($this->once())
            ->method('getExtra')
            ->willReturn(['parent_route']);
        $breadcrumbs = [
            [
                'label' => $parentLabel,
                'uri'   => '/bar/foo',
                'item'  => $menuItem
            ]
        ];

        $this->titleReaderRegistry->expects($this->once())
            ->method('getTitleByRoute')
            ->with($route)
            ->willReturn(null);

        $this->breadcrumbManager->expects($this->exactly(2))
            ->method('getBreadcrumbLabels')
            ->willReturn([$parentLabel]);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbs')
            ->willReturn($breadcrumbs);

        $this->userConfigManager->expects($this->exactly(5))
            ->method('get')
            ->willReturnMap([
                ['oro_navigation.breadcrumb_menu', false, false, null, 'application_menu'],
                ['oro_navigation.breadcrumb_menu', false, false, null, 'application_menu'],
                ['oro_navigation.title_suffix', false, false, null, 'Suffix'],
                ['oro_navigation.title_delimiter', false, false, null, '-'],
                ['oro_navigation.breadcrumb_menu', false, false, null, 'application_menu'],
            ]);

        $this->titleService->setPrefix('-');
        $this->titleService->loadByRoute($route);

        $this->assertEquals($parentLabel.' - Suffix', $this->titleService->getTemplate());
        $this->assertEquals($parentLabel, $this->titleService->getShortTemplate());
    }

    public function testLoadByRouteWithMenuName(): void
    {
        $route = 'test_route';
        $testTitle = 'Test Title';
        $menuName = 'application_menu';
        $parentLabel = 'Parent Label';
        $menuItem = $this->createMock(ItemInterface::class);
        $menuItem->expects($this->once())
            ->method('getExtra')
            ->willReturn(['parent_route']);
        $breadcrumbs = [
            [
                'label' => $parentLabel,
                'uri'   => '/bar/foo',
                'item'  => $menuItem
            ]
        ];

        $this->titleReaderRegistry->expects($this->once())
            ->method('getTitleByRoute')
            ->with($route)
            ->willReturn($testTitle);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbLabels')
            ->willReturn([$parentLabel]);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbs')
            ->willReturn($breadcrumbs);

        $this->userConfigManager->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['oro_navigation.title_suffix', false, false, null, 'Suffix'],
                ['oro_navigation.title_delimiter', false, false, null, '-'],
            ]);

        $this->titleService->setPrefix('-');
        $this->titleService->loadByRoute($route, $menuName);

        $this->assertEquals($testTitle.' - '.$parentLabel.' - Suffix', $this->titleService->getTemplate());
        $this->assertEquals($testTitle, $this->titleService->getShortTemplate());
    }

    public function testLoadByRouteWithPageTitleInsteadFirstBreadcrumbItem(): void
    {
        $childRoute = 'child_route';
        $childTitle = 'Child Title';
        $newChildTitle = 'New child title';
        $parentTitle = 'Parent Title';
        $childMenuItem = $this->createMock(ItemInterface::class);
        $childMenuItem->expects($this->once())
            ->method('getExtra')
            ->willReturn([$childRoute]);
        $parentMenuItem = $this->createMock(ItemInterface::class);
        $breadcrumbs = [
            [
                'label' => $childTitle,
                'uri'   => '/bar/foo',
                'item'  => $childMenuItem
            ],
            [
                'label' => $parentTitle,
                'uri'   => '/bar',
                'item'  => $parentMenuItem
            ]
        ];

        $this->titleReaderRegistry->expects($this->once())
            ->method('getTitleByRoute')
            ->with($childRoute)
            ->willReturn($newChildTitle);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbLabels')
            ->willReturn([$childTitle, $parentTitle]);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbs')
            ->willReturn($breadcrumbs);

        $this->userConfigManager->expects($this->exactly(4))
            ->method('get')
            ->willReturnMap([
                ['oro_navigation.title_delimiter', false, false, null, '-']
            ]);

        $this->titleService->loadByRoute($childRoute);

        $this->assertEquals($newChildTitle.' - '.$parentTitle, $this->titleService->getTemplate());
        $this->assertEquals($newChildTitle, $this->titleService->getShortTemplate());
    }

    public function testLoadByRouteWithoutTitleAndWithBreadcrumbs(): void
    {
        $childRoute = 'child_route';
        $childTitle = 'Child Title';
        $parentTitle = 'Parent Title';
        $childMenuItem = $this->createMock(ItemInterface::class);
        $childMenuItem->expects($this->once())
            ->method('getExtra')
            ->willReturn([$childRoute]);
        $parentMenuItem = $this->createMock(ItemInterface::class);
        $breadcrumbs = [
            [
                'label' => $childTitle,
                'uri'   => '/bar/foo',
                'item'  => $childMenuItem
            ],
            [
                'label' => $parentTitle,
                'uri'   => '/bar',
                'item'  => $parentMenuItem
            ]
        ];

        $this->titleReaderRegistry->expects($this->once())
            ->method('getTitleByRoute')
            ->with($childRoute)
            ->willReturn(null);

        $this->breadcrumbManager->expects($this->exactly(2))
            ->method('getBreadcrumbLabels')
            ->willReturn([$childTitle, $parentTitle]);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbs')
            ->willReturn($breadcrumbs);

        $this->userConfigManager->expects($this->exactly(5))
            ->method('get')
            ->willReturnMap([
                ['oro_navigation.breadcrumb_menu', false, false, null, 'application_menu'],
                ['oro_navigation.breadcrumb_menu', false, false, null, 'application_menu'],
                ['oro_navigation.title_delimiter', false, false, null, '-']
            ]);

        $this->titleService->loadByRoute($childRoute);

        $this->assertEquals($childTitle.' - '.$parentTitle, $this->titleService->getTemplate());
        $this->assertEquals($childTitle, $this->titleService->getShortTemplate());
    }

    public function testGetSerialized(): void
    {
        $this->titleService->setTemplate('test template');
        $this->titleService->setShortTemplate('test short template');
        $this->titleService->setParams(['prm1' => 'val1']);
        $this->titleService->setPrefix('test prefix');
        $this->titleService->setSuffix('test suffix');

        $this->assertEquals(
            '{"template":"test template","short_template":"test short template","params":{"prm1":"val1"},'
            . '"prefix":"test prefix","suffix":"test suffix"}',
            $this->titleService->getSerialized()
        );
    }

    public function testGetSerializedWithoutOptionalData(): void
    {
        $this->titleService->setTemplate('test template');
        $this->titleService->setShortTemplate('test short template');
        $this->titleService->setParams(['prm1' => 'val1']);

        $this->assertEquals(
            '{"template":"test template","short_template":"test short template","params":{"prm1":"val1"}}',
            $this->titleService->getSerialized()
        );
    }

    public function testGetSerializedWithEmptyData(): void
    {
        $this->assertEquals(
            '{"template":null,"short_template":null,"params":[]}',
            $this->titleService->getSerialized()
        );
    }

    public function testGetSerializedWithObjectInParams(): void
    {
        $value = new LocalizedFallbackValue();
        $value->setString('String');
        $this->titleService->setTemplate('test template');
        $this->titleService->setShortTemplate('test short template');
        $this->titleService->setParams(['localized_obj' => $value]);

        $this->assertEquals(
            '{"template":"test template","short_template":"test short template","params":{"localized_obj":"String"}}',
            $this->titleService->getSerialized()
        );
    }

    public function testCreateTitle(): void
    {
        $route = 'test_route';
        $testTitle = 'Test Title';
        $menuName = 'application_menu';
        $breadcrumbs = ['Parent Path'];

        $this->userConfigManager->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['oro_navigation.title_suffix', false, false, null, 'Suffix'],
                ['oro_navigation.title_delimiter', false, false, null, '-'],
            ]);

        $this->breadcrumbManager->expects($this->once())
            ->method('getBreadcrumbLabels')
            ->willReturn($breadcrumbs);

        $this->assertEquals(
            'Test Title - Parent Path - Suffix',
            $this->titleService->createTitle($route, $testTitle, $menuName)
        );
    }

    public function testCreateTitleForNullRoute(): void
    {
        $this->breadcrumbManager->expects($this->never())
            ->method('getBreadcrumbLabels');
        $this->userConfigManager->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['oro_navigation.title_suffix', false, false, null, 'Suffix'],
                ['oro_navigation.title_delimiter', false, false, null, '-']
            ]);

        $this->assertSame('Suffix', $this->titleService->createTitle(null, null));
    }
}
