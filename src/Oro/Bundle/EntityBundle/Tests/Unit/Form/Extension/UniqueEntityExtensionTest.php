<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\Form\Extension;

use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetadata;
use Oro\Bundle\EntityBundle\Form\Extension\UniqueEntityExtension;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UniqueEntityExtensionTest extends TestCase
{
    private const ENTITY = 'Namespace\EntityName';

    private ValidatorInterface&MockObject $validator;
    private ConfigProvider&MockObject $configProvider;
    private DoctrineHelper&MockObject $doctrineHelper;
    private ConfigInterface&MockObject $config;
    private ClassMetadata&MockObject $validatorMetadata;
    private FormBuilder&MockObject $builder;
    private UniqueEntityExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->config = $this->createMock(ConfigInterface::class);
        $this->validatorMetadata = $this->createMock(ClassMetadata::class);
        $this->builder = $this->createMock(FormBuilder::class);

        $translator = $this->createMock(TranslatorInterface::class);

        $metadata = $this->createMock(DoctrineClassMetadata::class);
        $metadata->expects($this->any())
            ->method('getName')
            ->willReturn(self::ENTITY);

        $this->extension = new UniqueEntityExtension(
            $this->validator,
            $translator,
            $this->configProvider,
            $this->doctrineHelper
        );
    }

    public function testWithoutClass(): void
    {
        $this->validatorMetadata->expects($this->never())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, []);
    }

    public function testForNotManageableEntity(): void
    {
        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(self::ENTITY)
            ->willReturn(false);

        $this->configProvider->expects($this->never())
            ->method('hasConfig');

        $this->validatorMetadata->expects($this->never())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, ['data_class' => self::ENTITY]);
    }

    public function testWithoutConfig(): void
    {
        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(self::ENTITY)
            ->willReturn(true);

        $this->configProvider->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY)
            ->willReturn(false);

        $this->validatorMetadata->expects($this->never())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, ['data_class' => self::ENTITY]);
    }

    public function testWithoutUniqueKeyOption(): void
    {
        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(self::ENTITY)
            ->willReturn(true);

        $this->configProvider->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY)
            ->willReturn(true);

        $this->configProvider->expects($this->once())
            ->method('getConfig')
            ->with(self::ENTITY)
            ->willReturn($this->config);

        $this->validatorMetadata->expects($this->never())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, ['data_class' => self::ENTITY]);
    }

    public function testWithConfigAndKeys(): void
    {
        $this->doctrineHelper->expects($this->once())
            ->method('isManageableEntity')
            ->with(self::ENTITY)
            ->willReturn(true);

        $this->configProvider->expects($this->once())
            ->method('hasConfig')
            ->with(self::ENTITY)
            ->willReturn(true);

        $this->configProvider->expects($this->any())
            ->method('getConfig')
            ->willReturn($this->config);

        $this->config->expects($this->any())
            ->method('get')
            ->with($this->isType('string'))
            ->willReturnCallback(function ($param) {
                $data = [
                    'label'      => 'label',
                    'unique_key' => ['keys' => ['tag0' => ['name' => 'test', 'key' => ['field']]]]
                ];

                return $data[$param];
            });

        $this->validator->expects($this->once())
            ->method('getMetadataFor')
            ->with(self::ENTITY)
            ->willReturn($this->validatorMetadata);

        $this->validatorMetadata->expects($this->once())
            ->method('addConstraint');

        $this->extension->buildForm($this->builder, ['data_class' => self::ENTITY]);
    }
}
