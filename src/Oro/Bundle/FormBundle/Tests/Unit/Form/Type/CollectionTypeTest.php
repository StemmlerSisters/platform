<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Form\Type;

use Oro\Bundle\FormBundle\Form\EventListener\CollectionTypeSubscriber;
use Oro\Bundle\FormBundle\Form\Type\CollectionType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType as BaseCollectionType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\FormBuilderInterface;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionTypeTest extends TestCase
{
    private CollectionType $type;

    #[\Override]
    protected function setUp(): void
    {
        $this->type = new CollectionType();
    }

    public function testBuildForm(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $builder->expects($this->once())
            ->method('addEventSubscriber')
            ->with($this->isInstanceOf(CollectionTypeSubscriber::class));

        $options = [];
        $this->type->buildForm($builder, $options);
    }

    /**
     * @dataProvider buildViewDataProvider
     */
    public function testBuildView(array $options, array $expectedVars): void
    {
        $form = $this->createMock(FormInterface::class);
        $view = new FormView();

        $this->type->buildView($view, $form, $options);

        foreach ($expectedVars as $key => $val) {
            $this->assertArrayHasKey($key, $view->vars);
            $this->assertEquals($val, $view->vars[$key]);
        }
    }

    public function buildViewDataProvider(): array
    {
        return [
            [
                'options'      => [
                    'handle_primary'       => false,
                    'show_form_when_empty' => false,
                    'prototype_name'       => '__name__',
                    'add_label'            => 'Add',
                    'allow_add_after'      => false,
                    'row_count_add'        => 1,
                    'row_count_initial'    => 1,
                ],
                'expectedVars' => [
                    'handle_primary'       => false,
                    'show_form_when_empty' => false,
                    'prototype_name'       => '__name__',
                    'add_label'            => 'Add',
                    'row_count_initial'    => 1,
                ],
            ],
            [
                'options'      => [
                    'handle_primary'       => true,
                    'show_form_when_empty' => true,
                    'prototype_name'       => '__custom_name__',
                    'add_label'            => 'Test Label',
                    'allow_add_after'      => false,
                    'row_count_add'        => 1,
                    'row_count_initial'    => 5,
                ],
                'expectedVars' => [
                    'handle_primary'       => true,
                    'show_form_when_empty' => true,
                    'prototype_name'       => '__custom_name__',
                    'add_label'            => 'Test Label',
                    'row_count_initial'    => 5,
                ],
            ],
        ];
    }

    public function testConfigureOptionsWithoutType(): void
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The required option "entry_type" is missing.');

        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);
        $resolver->resolve([]);
    }

    public function testConfigureOptions(): void
    {
        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);

        $options = [
            'entry_type' => 'test_type'
        ];
        $resolvedOptions = $resolver->resolve($options);
        $this->assertEquals(
            [
                'entry_type'           => 'test_type',
                'allow_add'            => true,
                'allow_delete'         => true,
                'by_reference'         => false,
                'prototype'            => true,
                'prototype_name'       => '__name__',
                'handle_primary'       => true,
                'show_form_when_empty' => true,
                'add_label'            => '',
                'allow_add_after'      => false,
                'row_count_add'        => 1,
                'row_count_initial'    => 1,
            ],
            $resolvedOptions
        );
    }

    public function testConfigureOptionsDisableAdd(): void
    {
        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);

        $options = [
            'entry_type' => 'test_type',
            'allow_add' => false
        ];
        $resolvedOptions = $resolver->resolve($options);
        $this->assertEquals(
            [
                'entry_type'           => 'test_type',
                'allow_add'            => false,
                'allow_delete'         => true,
                'by_reference'         => false,
                'prototype'            => true,
                'prototype_name'       => '__name__',
                'handle_primary'       => true,
                'show_form_when_empty' => false,
                'add_label'            => '',
                'allow_add_after'      => false,
                'row_count_add'        => 1,
                'row_count_initial'    => 1,
            ],
            $resolvedOptions
        );
    }

    public function testConfigureOptionsDisableShowFormWhenEmpty(): void
    {
        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);

        $options = [
            'entry_type'           => 'test_type',
            'show_form_when_empty' => false
        ];
        $resolvedOptions = $resolver->resolve($options);
        $this->assertEquals(
            [
                'entry_type'           => 'test_type',
                'allow_add'            => true,
                'allow_delete'         => true,
                'by_reference'         => false,
                'prototype'            => true,
                'prototype_name'       => '__name__',
                'handle_primary'       => true,
                'show_form_when_empty' => false,
                'add_label'            => '',
                'allow_add_after'      => false,
                'row_count_add'        => 1,
                'row_count_initial'    => 1,
            ],
            $resolvedOptions
        );
    }

    public function testConfigureOptionsCustomAddLabel(): void
    {
        $resolver = $this->getOptionsResolver();
        $this->type->configureOptions($resolver);

        $options = [
            'entry_type'           => 'test_type',
            'add_label'            => 'Test Label'
        ];
        $resolvedOptions = $resolver->resolve($options);
        $this->assertEquals(
            [
                'entry_type'           => 'test_type',
                'allow_add'            => true,
                'allow_delete'         => true,
                'by_reference'         => false,
                'prototype'            => true,
                'prototype_name'       => '__name__',
                'handle_primary'       => true,
                'show_form_when_empty' => true,
                'add_label'            => 'Test Label',
                'allow_add_after'      => false,
                'row_count_add'        => 1,
                'row_count_initial'    => 1,
            ],
            $resolvedOptions
        );
    }

    public function testGetParent(): void
    {
        $this->assertEquals(BaseCollectionType::class, $this->type->getParent());
    }

    public function testGetName(): void
    {
        $this->assertEquals('oro_collection', $this->type->getName());
    }

    private function getOptionsResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([]);

        return $resolver;
    }
}
