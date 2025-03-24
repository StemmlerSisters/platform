<?php

namespace Oro\Bundle\FilterBundle\Tests\Unit\Form\Type\Filter;

use Oro\Bundle\FilterBundle\Form\EventListener\DateFilterSubscriber;
use Oro\Bundle\FilterBundle\Form\Type\DateRangeType;
use Oro\Bundle\FilterBundle\Form\Type\Filter\DateRangeFilterType;
use Oro\Bundle\FilterBundle\Form\Type\Filter\FilterType;
use Oro\Bundle\FilterBundle\Provider\DateModifierProvider;
use Oro\Bundle\FilterBundle\Tests\Unit\Fixtures\CustomFormExtension;
use Oro\Bundle\FilterBundle\Tests\Unit\Form\Type\AbstractTypeTestCase;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\TestFrameworkBundle\Test\Form\MutableFormEventSubscriber;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateRangeFilterTypeTest extends AbstractTypeTestCase
{
    private DateRangeFilterType $type;

    #[\Override]
    protected function setUp(): void
    {
        $translator = $this->createTranslator();

        $subscriber = new MutableFormEventSubscriber($this->createMock(DateFilterSubscriber::class));

        $localeSettings = $this->createMock(LocaleSettings::class);
        $localeSettings->expects(self::any())
            ->method('getTimezone')
            ->willReturn('UTC');

        $this->type = new DateRangeFilterType($translator, new DateModifierProvider(), $subscriber);
        $this->formExtensions[] = new CustomFormExtension([
            new DateRangeType($localeSettings),
            new FilterType($translator)
        ]);
        $this->formExtensions[] = new PreloadedExtension([$this->type], []);

        parent::setUp();
    }

    #[\Override]
    protected function getTestFormType(): AbstractType
    {
        return $this->type;
    }

    /**
     * @dataProvider configureOptionsDataProvider
     */
    #[\Override]
    public function testConfigureOptions(array $defaultOptions, array $requiredOptions = []): void
    {
        $resolver = new OptionsResolver();
        $this->getTestFormType()->configureOptions($resolver);
        $resolvedOptions = $resolver->resolve([]);
        $resolvedOptions = array_intersect_key($resolvedOptions, $defaultOptions);
        self::assertEquals($defaultOptions, $resolvedOptions);
    }

    #[\Override]
    public function configureOptionsDataProvider(): array
    {
        return [
            [
                'defaultOptions' => [
                    'field_type' => DateRangeType::class,
                    'widget_options' => [
                        'showDatevariables' => true,
                        'showTime'          => false,
                        'showTimepicker'    => false,
                    ],
                    'operator_choices' => [
                        'oro.filter.form.label_date_type_between' => DateRangeFilterType::TYPE_BETWEEN,
                        'oro.filter.form.label_date_type_not_between' => DateRangeFilterType::TYPE_NOT_BETWEEN,
                        'oro.filter.form.label_date_type_more_than' => DateRangeFilterType::TYPE_MORE_THAN ,
                        'oro.filter.form.label_date_type_less_than' => DateRangeFilterType::TYPE_LESS_THAN,
                        'oro.filter.form.label_date_type_equals' => DateRangeFilterType::TYPE_EQUAL,
                        'oro.filter.form.label_date_type_not_equals' => DateRangeFilterType::TYPE_NOT_EQUAL
                    ],
                    'type_values' => [
                        'between'    => DateRangeFilterType::TYPE_BETWEEN,
                        'notBetween' => DateRangeFilterType::TYPE_NOT_BETWEEN,
                        'moreThan'   => DateRangeFilterType::TYPE_MORE_THAN,
                        'lessThan'   => DateRangeFilterType::TYPE_LESS_THAN,
                        'equal'      => DateRangeFilterType::TYPE_EQUAL,
                        'notEqual'   => DateRangeFilterType::TYPE_NOT_EQUAL
                    ]
                ]
            ]
        ];
    }

    #[\Override]
    public function bindDataProvider(): array
    {
        return [
            'empty' => [
                'bindData'      => [],
                'formData'      => ['type' => null, 'value' => ['start' => '', 'end' => ''], 'part' => null],
                'viewData'      => [
                    'value'          => [
                        'type'  => null,
                        'value' => ['start' => '', 'end' => ''],
                        'part'  => null
                    ],
                    'widget_options' => ['firstDay' => 1],
                ],
                'customOptions' => [
                    'widget_options' => ['firstDay' => 1]
                ]
            ],
        ];
    }

    public function testBuildView(): void
    {
        $form = $this->factory->create(get_class($this->type));
        $view = $form->createView();

        self::assertEquals(
            [
                'showDatevariables' => true,
                'showTime' => false,
                'showTimepicker' => false
            ],
            $view->vars['widget_options']
        );
        self::assertEquals(
            [
                DateModifierProvider::PART_VALUE => 'oro.filter.form.label_date_part.value',
                DateModifierProvider::PART_DOW => 'oro.filter.form.label_date_part.dayofweek',
                DateModifierProvider::PART_WEEK => 'oro.filter.form.label_date_part.week',
                DateModifierProvider::PART_DAY => 'oro.filter.form.label_date_part.day',
                DateModifierProvider::PART_MONTH => 'oro.filter.form.label_date_part.month',
                DateModifierProvider::PART_QUARTER => 'oro.filter.form.label_date_part.quarter',
                DateModifierProvider::PART_DOY => 'oro.filter.form.label_date_part.dayofyear',
                DateModifierProvider::PART_YEAR => 'oro.filter.form.label_date_part.year'
            ],
            $view->vars['date_parts']
        );
        self::assertEquals(
            [
                'value' => [
                    DateModifierProvider::VAR_NOW => 'oro.filter.form.label_date_var.now',
                    DateModifierProvider::VAR_TODAY => 'oro.filter.form.label_date_var.today',
                    DateModifierProvider::VAR_SOW => 'oro.filter.form.label_date_var.sow',
                    DateModifierProvider::VAR_SOM => 'oro.filter.form.label_date_var.som',
                    DateModifierProvider::VAR_SOQ => 'oro.filter.form.label_date_var.soq',
                    DateModifierProvider::VAR_SOY => 'oro.filter.form.label_date_var.soy',
                    DateModifierProvider::VAR_THIS_MONTH_W_Y => 'oro.filter.form.label_date_var.this_month_w_y',
                    DateModifierProvider::VAR_THIS_DAY_W_Y => 'oro.filter.form.label_date_var.this_day_w_y'
                ],
                'dayofweek' => [
                    DateModifierProvider::VAR_THIS_DAY => 'oro.filter.form.label_date_var.this_day'
                ],
                'week' => [
                    DateModifierProvider::VAR_THIS_WEEK => 'oro.filter.form.label_date_var.this_week'
                ],
                'day' => [
                    DateModifierProvider::VAR_THIS_DAY => 'oro.filter.form.label_date_var.this_day'
                ],
                'month' => [
                    DateModifierProvider::VAR_THIS_MONTH => 'oro.filter.form.label_date_var.this_month',
                    DateModifierProvider::VAR_FMQ => 'oro.filter.form.label_date_var.this_fmq'
                ],
                'quarter' => [
                    DateModifierProvider::VAR_THIS_QUARTER => 'oro.filter.form.label_date_var.this_quarter'
                ],
                'dayofyear' => [
                    DateModifierProvider::VAR_THIS_DAY => 'oro.filter.form.label_date_var.this_day',
                    DateModifierProvider::VAR_FDQ => 'oro.filter.form.label_date_var.this_fdq'
                ],
                'year' => [
                    DateModifierProvider::VAR_THIS_YEAR => 'oro.filter.form.label_date_var.this_year'
                ]
            ],
            $view->vars['date_vars']
        );
        self::assertEquals(
            [
                'between' => DateRangeFilterType::TYPE_BETWEEN,
                'notBetween' => DateRangeFilterType::TYPE_NOT_BETWEEN,
                'moreThan' => DateRangeFilterType::TYPE_MORE_THAN,
                'lessThan' => DateRangeFilterType::TYPE_LESS_THAN,
                'equal' => DateRangeFilterType::TYPE_EQUAL,
                'notEqual' => DateRangeFilterType::TYPE_NOT_EQUAL
            ],
            $view->vars['type_values']
        );
    }
}
