<?php

namespace Oro\Bundle\NavigationBundle\Tests\Unit\Layout\DataProvider;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\NavigationBundle\Layout\DataProvider\NavigationTitleProvider;
use Oro\Bundle\NavigationBundle\Provider\TitleService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NavigationTitleProviderTest extends TestCase
{
    private TitleService&MockObject $titleService;
    private ConfigManager&MockObject $userConfigManager;
    private NavigationTitleProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->titleService = $this->createMock(TitleService::class);
        $this->userConfigManager = $this->createMock(ConfigManager::class);

        $this->provider = new NavigationTitleProvider($this->titleService, $this->userConfigManager);
    }

    /**
     * @dataProvider getDataDataProvider
     */
    public function testGetTitle(string $routeName, array $params, string $title, string $expected): void
    {
        $this->titleService->expects($this->once())
            ->method('loadByRoute')
            ->with($routeName, 'frontend_menu')
            ->willReturn(null);

        $this->titleService->expects($this->once())
            ->method('setParams')
            ->with([])
            ->willReturn($this->titleService);

        $this->titleService->expects($this->once())
            ->method('render')
            ->with([], null, null, null, true)
            ->willReturn($title);

        $this->userConfigManager->expects($this->once())
            ->method('get')
            ->with('oro_navigation.title_delimiter')
            ->willReturn('-');

        $result = $this->provider->getTitle($routeName, $params);
        $this->assertEquals($expected, $result);
    }

    public function getDataDataProvider(): array
    {
        return [
            'existRoute' => [
                'routeName' => 'oro_frontend_root',
                'params' => [],
                'title' => 'Home Page',
                'expected' => 'Home Page'

            ],
            'existRouteWithSuffix' => [
                'routeName' => 'oro_frontend_root',
                'params' => [],
                'title' => '- Home Page',
                'expected' => 'Home Page'
            ]
        ];
    }
}
