<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Form\Type;

use Oro\Bundle\IntegrationBundle\Form\EventListener\ChannelFormSubscriber;
use Oro\Bundle\IntegrationBundle\Form\EventListener\DefaultOwnerSubscriber;
use Oro\Bundle\IntegrationBundle\Form\Type\ChannelType as IntegrationType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IntegrationTypeTest extends TestCase
{
    private FormBuilder&MockObject $builder;
    private IntegrationType $type;

    #[\Override]
    protected function setUp(): void
    {
        $this->builder = $this->createMock(FormBuilder::class);
        $integrationFS = $this->createMock(ChannelFormSubscriber::class);
        $defaultUserOwnerFS = $this->createMock(DefaultOwnerSubscriber::class);

        $this->type = new IntegrationType($defaultUserOwnerFS, $integrationFS);
    }

    public function testBuildForm(): void
    {
        $this->builder->expects($this->exactly(2))
            ->method('addEventSubscriber')
            ->withConsecutive(
                [$this->isInstanceOf(ChannelFormSubscriber::class)],
                [$this->isInstanceOf(DefaultOwnerSubscriber::class)]
            );

        $this->type->buildForm($this->builder, []);
    }

    public function testConfigureOptions(): void
    {
        $resolver = $this->createMock(OptionsResolver::class);
        $resolver->expects($this->once())
            ->method('setDefaults')
            ->with($this->isType('array'));
        $this->type->configureOptions($resolver);
    }
}
