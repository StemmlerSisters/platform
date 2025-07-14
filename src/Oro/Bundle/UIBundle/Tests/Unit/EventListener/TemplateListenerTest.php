<?php

namespace Oro\Bundle\UIBundle\Tests\Unit\EventListener;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\Rules\English\InflectorFactory;
use Oro\Bundle\UIBundle\EventListener\TemplateListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

class TemplateListenerTest extends TestCase
{
    private Request $request;
    private ViewEvent $event;
    private Environment&MockObject $twig;
    private TemplateListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->request = Request::create('/test/url');

        $this->event = new ViewEvent(
            $this->createMock(HttpKernelInterface::class),
            $this->request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        );

        $this->twig = $this->createMock(Environment::class);

        $loader = $this->createMock(FilesystemLoader::class);
        $loader->expects(self::any())
            ->method('getPaths')
            ->with('TestBundle')
            ->willReturn([realpath(__DIR__  . '/fixtures')]);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::any())
            ->method('get')
            ->willReturnMap([
                [Inflector::class, (new InflectorFactory())->build()],
                [Environment::class, $this->twig],
                ['twig.loader.native_filesystem', $loader]
            ]);

        $this->listener = new TemplateListener($container);
    }

    /**
     * @dataProvider controllerDataProvider
     */
    public function testOnKernelControllerPath(
        Template|string $inputTemplate,
        Template $expectedTemplate
    ): void {
        $this->request->attributes->set('_template', $inputTemplate);

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects(self::once())
            ->method('exists')
            ->with($expectedTemplate->getTemplate())
            ->willReturn(true);

        $this->twig->expects(self::once())
            ->method('getLoader')
            ->willReturn($loader);

        $this->listener->onKernelView($this->event);
        self::assertEquals($expectedTemplate, $this->request->attributes->get('_template'));
    }

    public function controllerDataProvider(): array
    {
        return [
            'exist legacy controller' => [
                'inputTemplate' => $this->templateWithController('@TestBundle/legacy-controller/test.html.twig'),
                'expectedTemplate' => $this->templateWithController('@TestBundle/LegacyController/test.html.twig'),
            ],
            'exist legacy controller with legacy action (underscore)' => [
                'inputTemplate' => $this->templateWithController(
                    '@TestBundle/legacy-controller/legacy_action.html.twig'
                ),
                'expectedTemplate' => $this->templateWithController(
                    '@TestBundle/LegacyController/legacyAction.html.twig'
                ),
            ],
            'exist legacy controller with legacy action (hyphen)' => [
                'inputTemplate' => $this->templateWithController(
                    '@TestBundle/legacy_controller/legacy-action.html.twig'
                ),
                'expectedTemplate' => $this->templateWithController(
                    '@TestBundle/LegacyController/legacyAction.html.twig'
                ),
            ],
            'manual template reference' => [
                'inputTemplate' => $this->templateWithController(
                    '@TestBundle/LegacyController/legacy_action.html.twig'
                ),
                'expectedTemplate' => $this->templateWithController(
                    '@TestBundle/LegacyController/legacy_action.html.twig'
                ),
            ],
            'exist legacy controller in string' => [
                'inputTemplate' => '@TestBundle/legacy-controller/test.html.twig',
                'expectedTemplate' => $this->templateWithController('@TestBundle/LegacyController/test.html.twig'),
            ],
            'exist legacy controller in formatted string' => [
                'inputTemplate' => 'TestBundle:legacy-controller:test.html.twig',
                'expectedTemplate' => $this->templateWithController('@TestBundle/LegacyController/test.html.twig'),
            ],
            'exist new controller' => [
                'inputTemplate' => $this->templateWithController('@TestBundle/new-controller/test.html.twig'),
                'expectedTemplate' => $this->templateWithController('@TestBundle/new-controller/test.html.twig'),
            ],
            'exist new controller with action' => [
                'inputTemplate' => $this->templateWithController('@TestBundle/new-controller/new_action.html.twig'),
                'expectedTemplate' => $this->templateWithController('@TestBundle/new-controller/new_action.html.twig'),
            ],
            'exist new controller in string' => [
                'inputTemplate' => '@TestBundle/new-controller/test.html.twig',
                'expectedTemplate' => $this->templateWithController('@TestBundle/new-controller/test.html.twig'),
            ],
            'both controllers' => [
                'inputTemplate' => $this->templateWithController('@TestBundle/both-controller/test.html.twig'),
                'expectedTemplate' => $this->templateWithController('@TestBundle/both-controller/test.html.twig'),
            ],
            'not exist controller' => [
                'inputTemplate' => $this->templateWithController('@TestBundle/not-exist-controller/test.html.twig'),
                'expectedTemplate' => $this->templateWithController('@TestBundle/not-exist-controller/test.html.twig'),
            ],
        ];
    }

    /**
     * @dataProvider templateDataProvider
     */
    public function testOnKernelViewWidgetTemplate(
        bool $containerExists,
        bool $widgetExists,
        Template|string $inputTemplate,
        Template $expectedTemplate,
        string $requestAttribute
    ): void {
        $this->request->{$requestAttribute}->set('_widgetContainer', 'container');
        $this->request->attributes->set('_template', $inputTemplate);

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects(self::atLeastOnce())
            ->method('exists')
            ->willReturnMap([
                [$this->templateWithContainer('container'), $containerExists],
                [$this->templateWithContainer('widget'), $widgetExists],
                ['@TestBundle/Default/container/test.html.twig', $containerExists],
                ['@TestBundle/Default/widget/test.html.twig', $widgetExists],
            ]);
        $this->twig->expects(self::atLeastOnce())
            ->method('getLoader')
            ->willReturn($loader);

        $this->listener->onKernelView($this->event);
        self::assertEquals($expectedTemplate, $this->request->attributes->get('_template'));
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function templateDataProvider(): array
    {
        return [
            'container yes, widget yes' => [
                'containerExists' => true,
                'widgetExists' => true,
                'inputTemplate' => $this->templateWithContainer(),
                'expectedTemplate' => $this->templateWithContainer('container'),
                'requestAttribute' => 'query'
            ],
            'container yes, widget no' => [
                'containerExists' => true,
                'widgetExists' => false,
                'inputTemplate' => $this->templateWithContainer(),
                'expectedTemplate' => $this->templateWithContainer('container'),
                'requestAttribute' => 'query'
            ],
            'container no, widget yes' => [
                'containerExists' => false,
                'widgetExists' => true,
                'inputTemplate' => $this->templateWithContainer(),
                'expectedTemplate' => $this->templateWithContainer('widget'),
                'requestAttribute' => 'query'
            ],
            'container no, widget no' => [
                'containerExists' => false,
                'widgetExists' => false,
                'inputTemplate' => $this->templateWithContainer(),
                'expectedTemplate' => $this->templateWithContainer(),
                'requestAttribute' => 'query'
            ],
            'post container yes, widget yes' => [
                'containerExists' => true,
                'widgetExists' => true,
                'inputTemplate' => $this->templateWithContainer(),
                'expectedTemplate' => $this->templateWithContainer('container'),
                'requestAttribute' => 'request'
            ],
            'post container yes, widget no' => [
                'containerExists' => true,
                'widgetExists' => false,
                'inputTemplate' => $this->templateWithContainer(),
                'expectedTemplate' => $this->templateWithContainer('container'),
                'requestAttribute' => 'request'
            ],
            'post container no, widget yes' => [
                'containerExists' => false,
                'widgetExists' => true,
                'inputTemplate' => $this->templateWithContainer(),
                'expectedTemplate' => $this->templateWithContainer('widget'),
                'requestAttribute' => 'request'
            ],
            'post container no, widget no' => [
                'containerExists' => false,
                'widgetExists' => false,
                'inputTemplate' => $this->templateWithContainer(),
                'expectedTemplate' => $this->templateWithContainer(),
                'requestAttribute' => 'request'],
            'template name as string' => [
                'containerExists' => true,
                'widgetExists' => false,
                'inputTemplate' => '@TestBundle/Default/test.html.twig',
                'expectedTemplate' => $this->templateWithContainer('container'),
                'requestAttribute' => 'query'
            ],
            'template object' => [
                'containerExists' => true,
                'widgetExists' => false,
                'inputTemplate' => '@TestBundle/Default/container/test.html.twig',
                'expectedTemplate' => $this->templateWithContainer('container'),
                'requestAttribute' => 'query'
            ],
            'template object with template name as string' => [
                'containerExists' => true,
                'widgetExists' => false,
                'inputTemplate' => '@TestBundle/Default/test.html.twig',
                'expectedTemplate' => $this->templateWithContainer('container'),
                'requestAttribute' => 'query'
            ],
            'template object with not exists template name as string' => [
                'containerExists' => false,
                'widgetExists' => false,
                'inputTemplate' => new Template(['template' => '@TestBundle/Default/test.html.twig']),
                'expectedTemplate' => new Template(['template' => '@TestBundle/Default/test.html.twig']),
                'requestAttribute' => 'query'
            ],
        ];
    }

    public function testProcessContainerForCustomWidgetContainer(): void
    {
        $expectedTemplate = '@TestBundle/Default/widget/Calendar/test.html.twig';

        $this->request->query->set('_widgetContainer', 'Calendar');
        $this->request->attributes->set('_template', '@TestBundle/Default/widget/test.html.twig');

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects(self::atLeastOnce())
            ->method('exists')
            ->willReturnMap([
                ['@TestBundle/Default/Calendar/widget/test.html.twig', false],
                [$expectedTemplate, true],
            ]);
        $this->twig->expects(self::atLeastOnce())
            ->method('getLoader')
            ->willReturn($loader);

        $this->listener->onKernelView($this->event);

        self::assertEquals($expectedTemplate, $this->request->attributes->get('_template')->getTemplate());
    }

    private function templateWithContainer(?string $container = null): Template
    {
        $template = new Template();
        $template->setTemplate('@TestBundle/Default/' . ($container ? $container . '/' : '') . 'test.html.twig');

        return $template;
    }

    private function templateWithController(string $name): Template
    {
        $template = new Template();
        $template->setTemplate($name);

        return $template;
    }
}
