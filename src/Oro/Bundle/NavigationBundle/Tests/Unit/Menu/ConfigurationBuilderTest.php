<?php

declare(strict_types=1);

namespace Oro\Bundle\NavigationBundle\Tests\Unit\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use Oro\Bundle\NavigationBundle\Configuration\ConfigurationProvider;
use Oro\Bundle\NavigationBundle\Event\ConfigureMenuEvent;
use Oro\Bundle\NavigationBundle\Menu\ConfigurationBuilder;
use Oro\Component\Config\Resolver\SystemAwareResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ConfigurationBuilderTest extends TestCase
{
    private MenuFactory $factory;
    private FactoryInterface&MockObject $menuFactory;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ConfigurationProvider&MockObject $configurationProvider;
    private ConfigurationBuilder $configurationBuilder;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new MenuFactory();
        $this->menuFactory = $this->createMock(FactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->configurationProvider = $this->createMock(ConfigurationProvider::class);

        $this->configurationBuilder = new ConfigurationBuilder(
            new SystemAwareResolver(),
            $this->menuFactory,
            $this->eventDispatcher,
            $this->configurationProvider
        );
    }

    /**
     * @dataProvider menuStructureProvider
     */
    public function testBuild(array $options): void
    {
        $this->configurationProvider->expects(self::once())
            ->method('getMenuTree')
            ->willReturn($options['tree']);
        $this->configurationProvider->expects(self::any())
            ->method('getMenuItems')
            ->willReturn($options['items'] ?? []);

        $menu = new MenuItem('navbar', $this->factory);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new ConfigureMenuEvent($this->menuFactory, $menu), 'oro_menu.configure.navbar');

        $this->configurationBuilder->build($menu, [], 'navbar');

        $this->assertCount(2, $menu->getChildren());
        $this->assertEquals($options['tree']['navbar']['type'], $menu->getExtra('type'));
        $this->assertCount(
            count($options['tree']['navbar']['children']['user_user_show']['children']),
            $menu->getChild('user_user_show')
        );
        $this->assertEquals('user_user_show', $menu->getChild('user_user_show')->getName());
    }

    /**
     * @dataProvider setAreaToExtraProvider
     */
    public function testSetAreaToExtra(array $options, string $expectedArea): void
    {
        $this->configurationProvider->expects(self::once())
            ->method('getMenuTree')
            ->willReturn($options['tree']);
        $this->configurationProvider->expects(self::once())
            ->method('getMenuItems')
            ->willReturn($options['items']);

        $menu = new MenuItem('navbar', $this->factory);
        $this->configurationBuilder->build($menu, [], 'navbar');

        $this->assertEquals($expectedArea, $menu->getExtra('scope_type'));
    }

    public function setAreaToExtraProvider(): array
    {
        return [
            'with no scope type specified' => [
                'options' => [
                    'items' => [
                        'homepage' => [
                            'name' => 'Home page 2',
                            'label' => 'Home page title',
                            'route' => 'oro_menu_index',
                            'translateDomain' => 'SomeBundle',
                            'translateParameters' => [],
                            'routeParameters' => [],
                            'extras' => []
                        ]
                    ],
                    'tree' => [
                        'navbar' => [
                            'type' => 'navbar',
                            'children' => [
                                'homepage' => [
                                    'position' => 7,
                                    'children' => []
                                ]
                            ]
                        ]
                    ]
                ],
                'expectedArea' => 'menu_default_visibility',
            ],
            'with scope type' => [
                'options' => [
                    'items' => [
                        'homepage' => [
                            'name' => 'Home page 2',
                            'label' => 'Home page title',
                            'route' => 'oro_menu_index',
                            'translateDomain' => 'SomeBundle',
                            'translateParameters' => [],
                            'routeParameters' => [],
                            'extras' => []
                        ]
                    ],
                    'tree' => [
                        'navbar' => [
                            'type' => 'navbar',
                            'scope_type' => 'frontend',
                            'children' => [
                                'homepage' => [
                                    'position' => 7,
                                    'children' => []
                                ]
                            ]
                        ]
                    ]
                ],
                'expectedArea' => 'frontend',
            ]
        ];
    }

    public function menuStructureProvider(): array
    {
        return [
            'full_menu' => [[
                'areas' => [],
                'templates' => [
                    'navbar' => [
                        'template' => '@OroNavigation/Menu/navbar.html.twig'
                    ],
                    'dropdown' => [
                        'template' => '@OroNavigation/Menu/dropdown.html.twig'
                    ]
                ],
                'items' => [
                    'homepage' => [
                        'name' => 'Home page 2',
                        'label' => 'Home page title',
                        'route' => 'oro_menu_index',
                        'translateDomain' => 'SomeBundle',
                        'translateParameters' => [],
                        'translate_disabled' => false,
                        'routeParameters' => [],
                        'extras' => []
                    ],
                    'user_registration_register' => [
                        'route' => 'oro_menu_submenu',
                        'translateDomain' => 'SomeBundle',
                        'translateParameters' => [],
                        'translate_disabled' => true,
                        'routeParameters' => [],
                        'extras' => []
                    ],
                    'user_user_show' => [
                        'translateDomain' => 'SomeBundle',
                        'translateParameters' => [],
                        'routeParameters' => [],
                        'extras' => []
                    ],
                ],
                'tree' => [
                    'navbar' => [
                        'type' => 'navbar',
                        'extras' => [
                            'brand' => 'Oro',
                            'brandLink' => '/'
                        ],
                        'children' => [
                            'user_user_show' => [
                                'position' => '10',
                                'children' => [
                                    'user_registration_register' => [
                                        'children' => []
                                    ]
                                ]
                            ],
                            'homepage' => [
                                'position' => 7,
                                'children' => []
                            ]
                        ]
                    ]
                ]
            ]]
        ];
    }

    public function testBuildDuplicatedItemTreeCallException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Item key "user_user_show" duplicated in tree menu "navbar".');

        $options = [
            'areas' => [],
            'items' => [
                'user_registration_register' => [
                    'route' => 'oro_menu_submenu',
                    'extras' => []
                ],
                'user_user_show' => [
                    'translateDomain' => 'SomeBundle',
                    'extras' => []
                ],
            ],
            'tree' => [
                'navbar' => [
                    'type' => 'navbar',
                    'extras' => [],
                    'children' => [
                        'user_user_show' => [
                            'position' => '10',
                            'children' => [
                                'user_registration_register' => [
                                    'children' => [
                                        'user_user_show' => [
                                            'children' => []
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $this->configurationProvider->expects(self::once())
            ->method('getMenuTree')
            ->willReturn($options['tree']);
        $this->configurationProvider->expects(self::any())
            ->method('getMenuItems')
            ->willReturn($options['items']);
        $menu = new MenuItem('navbar', $this->factory);
        $this->configurationBuilder->build($menu, [], 'navbar');
    }

    public function testWarningOption(): void
    {
        $warningText = 'Test Warning';
        $tree = [
            'navbarWithWarning' => [
                'children' => [],
                'warning' => $warningText
            ],
            'navbarWithoutWarning' => [
                'children' => []
            ]
        ];

        $this->configurationProvider->expects(self::any())
            ->method('getMenuTree')
            ->willReturn($tree);
        $this->configurationProvider->expects(self::any())
            ->method('getMenuItems')
            ->willReturn([]);

        $menu1 = new MenuItem('test', $this->factory);
        $this->configurationBuilder->build($menu1, [], 'navbarWithWarning');
        $this->assertEquals($warningText, $menu1->getExtra('warning'));

        $menu2 = new MenuItem('test', $this->factory);
        $this->configurationBuilder->build($menu2, [], 'navbarWithoutWarning');
        $this->assertNull($menu2->getExtra('warning'));
    }
}
