<?php

namespace Oro\Bundle\ConfigBundle\Tests\Unit\Form\EventListener;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ConfigBundle\Form\DataTransformer\ConfigFileDataTransformer;
use Oro\Bundle\ConfigBundle\Form\EventListener\ConfigSubscriber;
use Oro\Bundle\ConfigBundle\Form\Type\ConfigFileType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;

class ConfigSubscriberTest extends TestCase
{
    private ConfigManager&MockObject $configManager;
    private FormEvent&MockObject $event;
    private ConfigSubscriber $subscriber;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->subscriber = new ConfigSubscriber($this->configManager);

        $this->event = $this->createMock(FormEvent::class);
    }

    public function testPreSubmit(): void
    {
        $data = ['oro_user___level' => ['use_parent_scope_value' => true]];

        $form = $this->prepareForm(new IntegerType());
        $this->event->expects(self::any())
            ->method('getForm')
            ->willReturn($form);
        $this->event->expects(self::any())
            ->method('getData')
            ->willReturn($data);
        $this->configManager->expects(self::once())
            ->method('get')
            ->with('oro_user.level', true)
            ->willReturn(20);
        $this->event->expects(self::once())
            ->method('setData')
            ->with(\array_merge_recursive($data, [
                'oro_user___level' => ['value' => 20]
            ]));

        $this->subscriber->preSubmit($this->event);
    }

    public function testPreSubmitConfigFileType(): void
    {
        $data = ['oro_user___level' => ['use_parent_scope_value' => true,]];

        $transformer = $this->createMock(ConfigFileDataTransformer::class);
        $form = $this->prepareForm(new ConfigFileType($transformer));
        $this->event->expects(self::any())
            ->method('getForm')
            ->willReturn($form);
        $this->event->expects(self::any())
            ->method('getData')
            ->willReturn($data);
        $this->event->expects(self::once())
            ->method('setData')
            ->with(\array_merge_recursive($data, [
                'oro_user___level' => ['value' => [
                    'file' => null,
                    'emptyFile' => true
                ]]
            ]));

        $this->subscriber->preSubmit($this->event);
    }

    private function prepareForm($innerType)
    {
        $resolvedType = $this->createMock(ResolvedFormTypeInterface::class);
        $resolvedType->expects(self::any())
            ->method('getInnerType')
            ->willReturn($innerType);

        $config = $this->createMock(FormConfigInterface::class);
        $config->expects(self::any())
            ->method('getType')
            ->willReturn($resolvedType);

        $valueForm = $this->createMock(FormInterface::class);
        $valueForm->expects(self::any())
            ->method('getConfig')
            ->willReturn($config);

        $childForm = $this->createMock(FormInterface::class);
        $childForm->expects(self::any())
            ->method('get')
            ->with('value')
            ->willReturn($valueForm);

        $form = $this->createMock(FormInterface::class);
        $form->expects(self::any())
            ->method('get')
            ->willReturn($childForm);

        return $form;
    }
}
