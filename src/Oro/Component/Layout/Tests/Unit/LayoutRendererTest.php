<?php

namespace Oro\Component\Layout\Tests\Unit;

use Oro\Component\Layout\BlockView;
use Oro\Component\Layout\Form\FormRendererInterface;
use Oro\Component\Layout\Form\RendererEngine\FormRendererEngineInterface;
use Oro\Component\Layout\LayoutRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LayoutRendererTest extends TestCase
{
    private FormRendererInterface&MockObject $innerRenderer;
    private FormRendererEngineInterface&MockObject $formRenderer;
    private LayoutRenderer $renderer;

    #[\Override]
    protected function setUp(): void
    {
        $this->innerRenderer = $this->createMock(FormRendererInterface::class);
        $this->formRenderer = $this->createMock(FormRendererEngineInterface::class);
        $this->renderer = new LayoutRenderer($this->innerRenderer, $this->formRenderer);
    }

    public function testRenderBlock(): void
    {
        $expected = 'some rendered string';

        $view = new BlockView();

        $this->innerRenderer->expects(self::once())
            ->method('searchAndRenderBlock')
            ->with($this->identicalTo($view), 'widget')
            ->willReturn($expected);

        $result = $this->renderer->renderBlock($view);
        self::assertEquals($expected, $result);
    }

    public function testSetBlockTheme(): void
    {
        $theme = '@My/blocks.html.twig';

        $view = new BlockView();

        $this->innerRenderer->expects(self::once())
            ->method('setTheme')
            ->with($this->identicalTo($view), $theme);

        $this->renderer->setBlockTheme($view, $theme);
    }

    public function testSetFormTheme(): void
    {
        $theme = '@My/forms.html.twig';

        $this->formRenderer->expects(self::once())
            ->method('addDefaultThemes')
            ->with($theme);

        $this->renderer->setFormTheme($theme);
    }
}
