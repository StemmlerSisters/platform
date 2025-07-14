<?php

namespace Oro\Bundle\EmbeddedFormBundle\Tests\Unit\Twig;

use Oro\Bundle\EmbeddedFormBundle\Twig\BackLinkExtension;
use Oro\Component\Testing\Unit\TwigExtensionTestCaseTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class BackLinkExtensionTest extends TestCase
{
    use TwigExtensionTestCaseTrait;

    private RouterInterface&MockObject $router;
    private TranslatorInterface&MockObject $translator;
    private BackLinkExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $container = self::getContainerBuilder()
            ->add(RouterInterface::class, $this->router)
            ->add(TranslatorInterface::class, $this->translator)
            ->getContainer($this);

        $this->extension = new BackLinkExtension($container);
    }

    public function testShouldReplacePlaceholderWithProvidedUrlAndLinkText(): void
    {
        $id = 'test_id';
        $url = 'test_url';
        $text = 'test text';
        $translatedText = 'test translated text';
        $originalString = 'Before link {back_link|' . $text . '} After link';
        $expectedString = 'Before link <a href="' . $url . '">' . $translatedText . '</a> After link';

        $this->router->expects($this->once())
            ->method('generate')
            ->with('oro_embedded_form_submit', ['id' => $id])
            ->willReturn($url);
        $this->translator->expects($this->once())
            ->method('trans')
            ->with($text)
            ->willReturn($translatedText);

        $this->assertEquals(
            $expectedString,
            self::callTwigFilter($this->extension, 'back_link', [$originalString, $id])
        );
    }

    public function testShouldReplacePlaceholderWithReloadLinkAndLinkText(): void
    {
        $text = 'test text';
        $translatedText = 'test translated text';
        $originalString = 'Before link {back_link|' . $text . '} After link';
        $expectedLink = '<a href="#" onclick="window.location.reload(true); return false;">'
            . $translatedText
            . '</a>';
        $expectedString = 'Before link ' . $expectedLink . ' After link';

        $this->translator->expects($this->once())
            ->method('trans')
            ->with($text)
            ->willReturn($translatedText);

        $this->assertEquals(
            $expectedString,
            self::callTwigFilter($this->extension, 'back_link', [$originalString, null])
        );
    }

    public function testShouldReplacePlaceholderWithProvidedUrlAndDefaultLinkText(): void
    {
        $id = 'test_id';
        $url = 'test_url';
        $originalString = 'Before link {back_link} After link';
        $expectedString = 'Before link <a href="' . $url . '">Back</a> After link';

        $this->router->expects($this->once())
            ->method('generate')
            ->with('oro_embedded_form_submit', ['id' => $id])
            ->willReturn($url);
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('oro.embeddedform.back_link_default_text')
            ->willReturn('Back');

        $this->assertEquals(
            $expectedString,
            self::callTwigFilter($this->extension, 'back_link', [$originalString, $id])
        );
    }

    public function testShouldReturnOriginalStringWhenNoPlaceholderProvided(): void
    {
        $originalString = 'any string';

        $this->assertEquals(
            $originalString,
            self::callTwigFilter($this->extension, 'back_link', [$originalString, 'test_id'])
        );
    }
}
