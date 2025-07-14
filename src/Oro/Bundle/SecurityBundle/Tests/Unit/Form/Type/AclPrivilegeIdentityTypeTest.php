<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Form\Type;

use Oro\Bundle\SecurityBundle\Form\Type\AclPrivilegeIdentityType;
use Oro\Bundle\SecurityBundle\Form\Type\ObjectLabelType;
use Oro\Bundle\SecurityBundle\Model\AclPrivilegeIdentity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AclPrivilegeIdentityTypeTest extends TestCase
{
    private AclPrivilegeIdentityType $formType;

    #[\Override]
    protected function setUp(): void
    {
        $this->formType = new AclPrivilegeIdentityType();
    }

    public function testBuildForm(): void
    {
        $builder = $this->createMock(FormBuilder::class);
        $builder->expects($this->exactly(2))
            ->method('add')
            ->withConsecutive(
                ['id', HiddenType::class, ['required' => true]],
                ['name', ObjectLabelType::class, ['required' => false]]
            );
        $this->formType->buildForm($builder, []);
    }

    public function testConfigureOptions(): void
    {
        $resolver = $this->createMock(OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with(
                [
                    'data_class' => AclPrivilegeIdentity::class
                ]
            );
        $this->formType->configureOptions($resolver);
    }
}
