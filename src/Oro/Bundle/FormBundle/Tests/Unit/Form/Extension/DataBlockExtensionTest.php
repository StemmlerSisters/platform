<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Form\Extension;

use Oro\Bundle\FormBundle\Form\Extension\DataBlockExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DataBlockExtensionTest extends TestCase
{
    private array $options = ['block' => 1, 'subblock' => 1, 'block_config' => 1, 'tooltip' => 1];

    private DataBlockExtension $formExtension;

    #[\Override]
    protected function setUp(): void
    {
        $this->formExtension = new DataBlockExtension();
    }

    public function testConfigureOptions(): void
    {
        $resolver = new OptionsResolver();
        $this->formExtension->configureOptions($resolver);

        $this->assertEquals(
            [],
            $resolver->resolve()
        );
        $this->assertEquals(
            $this->options,
            $resolver->resolve($this->options)
        );
    }

    public function testBuildView(): void
    {
        $formView = new FormView();

        $form = $this->createMock(Form::class);

        $this->formExtension->buildView($formView, $form, $this->options);

        $this->assertEquals($this->options['block'], $formView->vars['block']);
        $this->assertEquals($this->options['subblock'], $formView->vars['subblock']);
        $this->assertEquals($this->options['block_config'], $formView->vars['block_config']);
        $this->assertEquals($this->options['tooltip'], $formView->vars['tooltip']);
    }
}
