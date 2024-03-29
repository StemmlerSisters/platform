<?php

namespace Oro\Bundle\ThemeBundle\Form\Type;

use Oro\Bundle\FormBundle\Form\Type\OroEntitySelectOrCreateInlineType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type to select ThemeConfiguration entities.
 */
class ThemeConfigurationSelectType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'autocomplete_alias' => ThemeConfigurationType::class,
                'create_form_route' => 'oro_theme_configuration_create',
                'configs' => [
                    'placeholder' => 'oro.theme.themeconfiguration.form.choose',
                ],
                'attr' => [
                    'class' => 'oro-theme-configuration-select',
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'oro_theme_configuration_select';
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): ?string
    {
        return OroEntitySelectOrCreateInlineType::class;
    }
}
