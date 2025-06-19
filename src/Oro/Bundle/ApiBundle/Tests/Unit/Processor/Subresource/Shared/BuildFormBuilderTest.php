<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\Shared;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Exception\RuntimeException;
use Oro\Bundle\ApiBundle\Form\FormHelper;
use Oro\Bundle\ApiBundle\Form\Guesser\DataTypeGuesser;
use Oro\Bundle\ApiBundle\Metadata\AssociationMetadata;
use Oro\Bundle\ApiBundle\Metadata\EntityMetadata;
use Oro\Bundle\ApiBundle\Processor\Subresource\Shared\BuildFormBuilder;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\User;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity\UserProfile;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\Subresource\ChangeRelationshipProcessorTestCase;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class BuildFormBuilderTest extends ChangeRelationshipProcessorTestCase
{
    private const string TEST_PARENT_CLASS_NAME = 'Test\Entity';
    private const string TEST_ASSOCIATION_NAME = 'testAssociation';

    private FormFactoryInterface&MockObject $formFactory;
    private ContainerInterface&MockObject $container;
    private BuildFormBuilder $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->container = $this->createMock(ContainerInterface::class);

        $this->processor = new BuildFormBuilder(
            new FormHelper(
                $this->formFactory,
                new DataTypeGuesser([]),
                PropertyAccess::createPropertyAccessor(),
                $this->container
            )
        );

        $this->context->setParentClassName(self::TEST_PARENT_CLASS_NAME);
        $this->context->setAssociationName(self::TEST_ASSOCIATION_NAME);
    }

    public function testProcessWhenFormBuilderAlreadyExists(): void
    {
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $this->context->setFormBuilder($formBuilder);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessWhenFormAlreadyExists(): void
    {
        $form = $this->createMock(FormInterface::class);

        $this->context->setForm($form);
        $this->processor->process($this->context);
        self::assertFalse($this->context->hasFormBuilder());
        self::assertSame($form, $this->context->getForm());
    }

    public function testProcessWhenNoParentEntity(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The parent entity object must be added to the context before creation of the form builder.'
        );

        $this->formFactory->expects(self::never())
            ->method('createNamedBuilder');

        $this->context->setParentConfig(new EntityDefinitionConfig());
        $this->context->setParentMetadata(new EntityMetadata('Test\Entity'));
        $this->processor->process($this->context);
    }

    public function testProcessWithDefaultOptions(): void
    {
        $parentEntity = new \stdClass();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->addField(self::TEST_ASSOCIATION_NAME);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME));

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class'           => self::TEST_PARENT_CLASS_NAME,
                    'validation_groups'    => ['Default', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation'    => false,
                    'api_context'          => $this->context
                ]
            )
            ->willReturn($formBuilder);

        $formBuilder->expects(self::once())
            ->method('add')
            ->with(self::TEST_ASSOCIATION_NAME, null, [])
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessWithFormOptionsInContext(): void
    {
        $parentEntity = new \stdClass();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->addField(self::TEST_ASSOCIATION_NAME);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME));

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class' => self::TEST_PARENT_CLASS_NAME,
                    'validation_groups' => ['Default', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation' => true,
                    'api_context' => $this->context,
                    'another_option' => 'val'
                ]
            )
            ->willReturn($formBuilder);

        $formBuilder->expects(self::once())
            ->method('add')
            ->with(self::TEST_ASSOCIATION_NAME, null, [])
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->context->setFormOptions(['enable_validation' => true, 'another_option' => 'val']);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessForParentApiResourceBasedOnManageableEntity(): void
    {
        $parentEntityClass = UserProfile::class;
        $parentBaseEntityClass = User::class;
        $parentEntity = new User();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->setParentResourceClass($parentBaseEntityClass);
        $parentConfig->addField(self::TEST_ASSOCIATION_NAME);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME));

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class'           => $parentEntityClass,
                    'validation_groups'    => ['Default', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation'    => false,
                    'api_context'          => $this->context
                ]
            )
            ->willReturn($formBuilder);

        $formBuilder->expects(self::once())
            ->method('add')
            ->with(self::TEST_ASSOCIATION_NAME, null, [])
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessForParentApiResourceBasedOnManageableEntityWithCustomProcessorToLoadParentEntity(): void
    {
        $parentEntityClass = UserProfile::class;
        $parentBaseEntityClass = User::class;
        $parentEntity = new UserProfile();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->setParentResourceClass($parentBaseEntityClass);
        $parentConfig->addField(self::TEST_ASSOCIATION_NAME);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME));

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class'           => $parentEntityClass,
                    'validation_groups'    => ['Default', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation'    => false,
                    'api_context'          => $this->context
                ]
            )
            ->willReturn($formBuilder);

        $formBuilder->expects(self::once())
            ->method('add')
            ->with(self::TEST_ASSOCIATION_NAME, null, [])
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentClassName($parentEntityClass);
        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessWithCustomOptions(): void
    {
        $parentEntity = new \stdClass();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->setFormOptions(['validation_groups' => ['test', 'api']]);
        $associationConfig = $parentConfig->addField(self::TEST_ASSOCIATION_NAME);
        $associationConfig->setPropertyPath('realAssociationName');
        $associationConfig->setFormType('customType');
        $associationConfig->setFormOptions(['trim' => false]);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME))
            ->setPropertyPath('realAssociationName');

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class'           => self::TEST_PARENT_CLASS_NAME,
                    'validation_groups'    => ['test', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation'    => false,
                    'api_context'          => $this->context
                ]
            )
            ->willReturn($formBuilder);

        $formBuilder->expects(self::once())
            ->method('add')
            ->with(
                self::TEST_ASSOCIATION_NAME,
                'customType',
                ['property_path' => 'realAssociationName', 'trim' => false]
            )
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessWhenAssociationShouldNotBeMapped(): void
    {
        $parentEntity = new \stdClass();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->addField(self::TEST_ASSOCIATION_NAME);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME))
            ->setPropertyPath(ConfigUtil::IGNORE_PROPERTY_PATH);

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class'           => self::TEST_PARENT_CLASS_NAME,
                    'validation_groups'    => ['Default', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation'    => false,
                    'api_context'          => $this->context
                ]
            )
            ->willReturn($formBuilder);

        $formBuilder->expects(self::once())
            ->method('add')
            ->with(self::TEST_ASSOCIATION_NAME, null, ['mapped' => false])
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessForCustomEventSubscriber(): void
    {
        $parentEntity = new \stdClass();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $eventSubscriberServiceId = 'test_event_subscriber';
        $eventSubscriber = $this->createMock(EventSubscriberInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->addField(self::TEST_ASSOCIATION_NAME);
        $parentConfig->setFormEventSubscribers([$eventSubscriberServiceId]);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME));

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class'           => self::TEST_PARENT_CLASS_NAME,
                    'validation_groups'    => ['Default', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation'    => false,
                    'api_context'          => $this->context
                ]
            )
            ->willReturn($formBuilder);

        $this->container->expects(self::once())
            ->method('get')
            ->with($eventSubscriberServiceId)
            ->willReturn($eventSubscriber);

        $formBuilder->expects(self::once())
            ->method('addEventSubscriber')
            ->with(self::identicalTo($eventSubscriber));
        $formBuilder->expects(self::once())
            ->method('add')
            ->with(self::TEST_ASSOCIATION_NAME, null, [])
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessForCustomEventSubscriberInjectedAsService(): void
    {
        $parentEntity = new \stdClass();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $eventSubscriber = $this->createMock(EventSubscriberInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->addField(self::TEST_ASSOCIATION_NAME);
        $parentConfig->setFormEventSubscribers([$eventSubscriber]);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME));

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class'           => self::TEST_PARENT_CLASS_NAME,
                    'validation_groups'    => ['Default', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation'    => false,
                    'api_context'          => $this->context
                ]
            )
            ->willReturn($formBuilder);

        $formBuilder->expects(self::once())
            ->method('addEventSubscriber')
            ->with(self::identicalTo($eventSubscriber));
        $formBuilder->expects(self::once())
            ->method('add')
            ->with(self::TEST_ASSOCIATION_NAME, null, [])
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }

    public function testProcessForCustomEventSubscriberAndCustomFormType(): void
    {
        $parentEntity = new \stdClass();
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        $parentConfig = new EntityDefinitionConfig();
        $parentConfig->addField(self::TEST_ASSOCIATION_NAME);
        $parentConfig->setFormType('test_form');
        $parentConfig->setFormEventSubscribers(['test_event_subscriber']);

        $parentMetadata = new EntityMetadata('Test\Entity');
        $parentMetadata->addAssociation(new AssociationMetadata(self::TEST_ASSOCIATION_NAME));

        $this->formFactory->expects(self::once())
            ->method('createNamedBuilder')
            ->with(
                null,
                FormType::class,
                $parentEntity,
                [
                    'data_class'           => self::TEST_PARENT_CLASS_NAME,
                    'validation_groups'    => ['Default', 'api'],
                    'extra_fields_message' => FormHelper::EXTRA_FIELDS_MESSAGE,
                    'enable_validation'    => false,
                    'api_context'          => $this->context
                ]
            )
            ->willReturn($formBuilder);

        $this->container->expects(self::never())
            ->method('get');

        $formBuilder->expects(self::never())
            ->method('addEventSubscriber');
        $formBuilder->expects(self::once())
            ->method('add')
            ->with(self::TEST_ASSOCIATION_NAME, null, [])
            ->willReturn($this->createMock(FormBuilderInterface::class));

        $this->context->setParentConfig($parentConfig);
        $this->context->setParentMetadata($parentMetadata);
        $this->context->setParentEntity($parentEntity);
        $this->processor->process($this->context);
        self::assertSame($formBuilder, $this->context->getFormBuilder());
    }
}
