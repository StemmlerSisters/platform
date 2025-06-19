<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\FormType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NameValuePairType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, $options['name_options'])
            ->add('value', TextType::class, $options['value_options']);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'name_options'  => [],
                'value_options' => []
            ]
        );
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'test_name_value_pair';
    }
}
