<?php

namespace Oro\Bundle\TranslationBundle\Tests\Unit\Twig;

use Oro\Bundle\TranslationBundle\Helper\TranslationsDatagridRouteHelper;
use Oro\Bundle\TranslationBundle\Twig\TranslationExtension;
use Oro\Component\Testing\Unit\TwigExtensionTestCaseTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TranslationExtensionTest extends TestCase
{
    use TwigExtensionTestCaseTrait;

    private TranslationsDatagridRouteHelper&MockObject $translationRouteHelper;
    private TranslationExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->translationRouteHelper = $this->createMock(TranslationsDatagridRouteHelper::class);

        $container = self::getContainerBuilder()
            ->add('oro_translation.helper.translation_route', $this->translationRouteHelper)
            ->getContainer($this);

        $this->extension = new TranslationExtension($container, true, true);
    }

    public function testIsDebugTranslator(): void
    {
        $this->assertTrue(
            self::callTwigFunction($this->extension, 'oro_translation_debug_translator', [])
        );
    }

    public function testIsDebugJsTranslations(): void
    {
        $this->assertTrue(
            self::callTwigFunction($this->extension, 'oro_translation_debug_js_translations', [])
        );
    }
}
