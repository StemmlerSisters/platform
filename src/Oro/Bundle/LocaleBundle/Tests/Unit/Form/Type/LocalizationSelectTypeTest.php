<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Form\Type;

use Oro\Bundle\FormBundle\Form\Type\OroEntitySelectOrCreateInlineType;
use Oro\Bundle\LocaleBundle\Form\Type\LocalizationSelectType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocalizationSelectTypeTest extends TestCase
{
    private LocalizationSelectType $type;

    #[\Override]
    protected function setUp(): void
    {
        $this->type = new LocalizationSelectType();
    }

    public function testGetParent(): void
    {
        $this->assertEquals(OroEntitySelectOrCreateInlineType::class, $this->type->getParent());
    }

    public function testConfigureOptions(): void
    {
        $resolver = $this->createMock(OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->isType('array'))
            ->willReturnCallback(function (array $options) use ($resolver) {
                $this->assertArrayHasKey('autocomplete_alias', $options);
                $this->assertArrayHasKey('create_form_route', $options);
                $this->assertArrayHasKey('configs', $options);
                $this->assertEquals('oro_localization', $options['autocomplete_alias']);
                $this->assertEquals('oro_locale_localization_create', $options['create_form_route']);
                $this->assertEquals(
                    ['placeholder' => 'oro.locale.localization.form.placeholder.choose'],
                    $options['configs']
                );

                return $resolver;
            });

        $this->type->configureOptions($resolver);
    }
}
