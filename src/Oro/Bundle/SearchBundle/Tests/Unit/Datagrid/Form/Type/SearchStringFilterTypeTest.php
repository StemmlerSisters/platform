<?php

namespace Oro\Bundle\SearchBundle\Tests\Unit\Datagrid\Form\Type;

use Oro\Bundle\FilterBundle\Form\Type\Filter\FilterType;
use Oro\Bundle\FilterBundle\Form\Type\Filter\TextFilterType;
use Oro\Bundle\FilterBundle\Tests\Unit\Fixtures\CustomFormExtension;
use Oro\Bundle\FilterBundle\Tests\Unit\Form\Type\AbstractTypeTestCase;
use Oro\Bundle\SearchBundle\Datagrid\Form\Type\SearchStringFilterType;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\AbstractType;

class SearchStringFilterTypeTest extends AbstractTypeTestCase
{
    private SearchStringFilterType $type;

    #[\Override]
    protected function setUp(): void
    {
        $translator = $this->createTranslator();
        $this->type = new SearchStringFilterType($translator);

        $this->formExtensions[] = new CustomFormExtension([
            new FilterType($translator),
            new TextFilterType($translator)
        ]);
        $this->formExtensions[] = new PreloadedExtension([$this->type], []);

        parent::setUp();
    }

    #[\Override]
    protected function getTestFormType(): AbstractType
    {
        return $this->type;
    }

    public function testGetBlockPrefix(): void
    {
        self::assertEquals(SearchStringFilterType::NAME, $this->type->getBlockPrefix());
    }

    public function testGetParent(): void
    {
        self::assertEquals(TextFilterType::class, $this->type->getParent());
    }

    #[\Override]
    public function configureOptionsDataProvider(): array
    {
        return [
            [
                'defaultOptions' => [
                    'operator_choices' => [
                        'oro.filter.form.label_type_contains' => TextFilterType::TYPE_CONTAINS,
                        'oro.filter.form.label_type_not_contains' => TextFilterType::TYPE_NOT_CONTAINS,
                        'oro.filter.form.label_type_equals' => TextFilterType::TYPE_EQUAL,
                    ]
                ]
            ]
        ];
    }

    #[\Override]
    public function bindDataProvider(): array
    {
        return [
            'simple text' => [
                'bindData' => ['type' => TextFilterType::TYPE_CONTAINS, 'value' => 'text'],
                'formData' => ['type' => TextFilterType::TYPE_CONTAINS, 'value' => 'text'],
                'viewData' => [
                    'value' => ['type' => TextFilterType::TYPE_CONTAINS, 'value' => 'text'],
                ],
            ],
        ];
    }
}
