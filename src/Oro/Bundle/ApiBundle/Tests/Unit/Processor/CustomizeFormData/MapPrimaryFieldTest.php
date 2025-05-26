<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\CustomizeFormData;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Form\Extension\CustomizeFormDataExtension;
use Oro\Bundle\ApiBundle\Form\Extension\ValidationExtension;
use Oro\Bundle\ApiBundle\Form\FormValidationHandler;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\CustomizeFormDataContext;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\CustomizeFormDataEventDispatcher;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\CustomizeFormDataHandler;
use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\MapPrimaryField;
use Oro\Bundle\ApiBundle\Processor\FormContext;
use Oro\Bundle\ApiBundle\Provider\ConfigProvider;
use Oro\Bundle\ApiBundle\Provider\MetadataProvider;
use Oro\Bundle\ApiBundle\Request\ApiAction;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\FormType\NameContainerType;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\FormType\RenamedNameContainerType;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\FormType\RestrictedNameContainerType;
use Oro\Bundle\ApiBundle\Tests\Unit\Processor\FormContextStub;
use Oro\Component\ChainProcessor\ActionProcessorInterface;
use Oro\Component\Testing\Unit\PreloadedExtension;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\Constraints\Form as FormConstraint;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class MapPrimaryFieldTest extends CustomizeFormDataProcessorTestCase
{
    private ActionProcessorInterface&MockObject $customizationProcessor;
    private CustomizeFormDataHandler $customizationHandler;
    private FormContext $formContext;
    private ValidatorInterface $validator;
    private FormValidationHandler $formValidationHandler;
    private MapPrimaryField $processor;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->customizationProcessor = $this->createMock(ActionProcessorInterface::class);
        $this->customizationHandler = new CustomizeFormDataHandler($this->customizationProcessor);
        $this->validator = Validation::createValidator();

        /* @var ClassMetadata $metadata */
        $metadata = $this->validator->getMetadataFor(Form::class);
        $metadata->addConstraint(new FormConstraint());
        $metadata->addPropertyConstraint('children', new Assert\Valid());

        $configProvider = $this->createMock(ConfigProvider::class);
        $metadataProvider = $this->createMock(MetadataProvider::class);
        $this->formContext = new FormContextStub($configProvider, $metadataProvider);
        $this->formContext->setAction(ApiAction::UPDATE);
        $this->formContext->setVersion('1.1');
        $this->formContext->getRequestType()->add(RequestType::REST);

        $this->processor = new MapPrimaryField(
            PropertyAccess::createPropertyAccessor(),
            'Unknown enabled group.',
            'enabledRole',
            'roles',
            'name',
            'enabled'
        );

        $this->customizationProcessor->expects(self::any())
            ->method('createContext')
            ->willReturnCallback(function () {
                return new CustomizeFormDataContext();
            });
        $this->customizationProcessor->expects(self::any())
            ->method('process')
            ->willReturnCallback(function (CustomizeFormDataContext $context) {
                if (Entity\Account::class === $context->getClassName()) {
                    $this->processor->process($context);
                }
            });

        $this->formValidationHandler = new FormValidationHandler(
            $this->validator,
            new CustomizeFormDataEventDispatcher($this->customizationHandler),
            PropertyAccess::createPropertyAccessor()
        );
    }

    #[\Override]
    protected function getFormExtensions(): array
    {
        return [
            new PreloadedExtension(
                [],
                [
                    FormType::class => [
                        new ValidationExtension($this->validator),
                        new CustomizeFormDataExtension($this->customizationProcessor, $this->customizationHandler)
                    ]
                ]
            )
        ];
    }

    private function getFormBuilder(?EntityDefinitionConfig $config): FormBuilderInterface
    {
        $this->formContext->setConfig($config);

        return $this->createFormBuilder()->create(
            '',
            FormType::class,
            [
                'data_class'                          => Entity\Account::class,
                'enable_validation'                   => false,
                CustomizeFormDataHandler::API_CONTEXT => $this->formContext
            ]
        );
    }

    private function processForm(
        ?EntityDefinitionConfig $config,
        Entity\Account $data,
        array $submittedData,
        array $itemOptions = [],
        string $entryType = NameContainerType::class
    ): FormInterface {
        $formBuilder = $this->getFormBuilder($config);
        $formBuilder->add('enabledRole', null, array_merge(['mapped' => false], $itemOptions));
        $formBuilder->add(
            'roles',
            CollectionType::class,
            [
                'by_reference'  => false,
                'allow_add'     => true,
                'allow_delete'  => true,
                'entry_type'    => $entryType,
                'entry_options' => array_merge(['data_class' => Entity\Role::class], $itemOptions)
            ]
        );

        $form = $formBuilder->getForm();
        $form->setData($data);
        $form->submit($submittedData, false);
        $this->validateForm($form);

        return $form;
    }

    private function validateForm(FormInterface $form): void
    {
        $this->formValidationHandler->preValidate($form);
        $this->formValidationHandler->validate($form);
        $this->formValidationHandler->postValidate($form);
    }

    private function addRole(Entity\Account $data, string $name, bool $enabled): Entity\Role
    {
        $role = new Entity\Role();
        $role->setName($name);
        $role->setEnabled($enabled);
        $data->addRole($role);

        return $role;
    }

    public function testProcessWithoutConfigShouldWorkAsRegularForm(): void
    {
        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm(
            null,
            $data,
            [
                'enabledRole' => 'role1',
                'roles'       => [
                    ['name' => 'role1'],
                    ['name' => 'role2'],
                    ['name' => 'role3']
                ]
            ]
        );
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertFalse($role1->isEnabled());
        self::assertTrue($role2->isEnabled());
        self::assertCount(3, $data->getRoles());
    }

    public function testProcessWithoutAssociationConfigShouldWorkAsRegularForm(): void
    {
        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm(
            new EntityDefinitionConfig(),
            $data,
            [
                'enabledRole' => 'role1',
                'roles'       => [
                    ['name' => 'role1'],
                    ['name' => 'role2']
                ]
            ]
        );
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertFalse($role1->isEnabled());
        self::assertTrue($role2->isEnabled());
    }

    public function testProcessWithoutPrimaryFieldFormFieldShouldWorkAsRegularForm(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $formBuilder = $this->getFormBuilder($config);
        $formBuilder->add(
            'roles',
            CollectionType::class,
            [
                'by_reference'  => false,
                'allow_add'     => true,
                'allow_delete'  => true,
                'entry_type'    => NameContainerType::class,
                'entry_options' => ['data_class' => Entity\Role::class]
            ]
        );
        $form = $formBuilder->getForm();
        $form->setData($data);
        $form->submit(
            [
                'roles' => [
                    ['name' => 'role1'],
                    ['name' => 'role2'],
                    ['name' => 'role3']
                ]
            ],
            false
        );
        $this->validateForm($form);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertFalse($role1->isEnabled());
        self::assertTrue($role2->isEnabled());
        self::assertCount(3, $data->getRoles());
    }

    public function testProcessWithoutAssociationFormFieldShouldWorkAsRegularForm(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $formBuilder = $this->getFormBuilder($config);
        $formBuilder->add('enabledRole', null, ['mapped' => false]);
        $form = $formBuilder->getForm();
        $form->setData($data);
        $form->submit(['enabledRole' => 'role1'], false);
        $this->validateForm($form);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertFalse($role1->isEnabled());
        self::assertTrue($role2->isEnabled());
    }

    public function testProcessWhenPrimaryFieldAndAssociationAreNotSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm($config, $data, []);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertFalse($role1->isEnabled());
        self::assertTrue($role2->isEnabled());
    }

    public function testProcessWhenPrimaryFieldIsNotSubmittedButAssociationIsSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm(
            $config,
            $data,
            [
                'roles' => [
                    ['name' => 'role1'],
                    ['name' => 'role2']
                ]
            ]
        );
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertFalse($role1->isEnabled());
        self::assertTrue($role2->isEnabled());
    }

    public function testProcessWhenEmptyValueForPrimaryFieldIsSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm($config, $data, ['enabledRole' => '']);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertFalse($role1->isEnabled());
        self::assertFalse($role2->isEnabled());
    }

    public function testProcessWhenEmptyValueForPrimaryFieldIsSubmittedAndAssociationIsSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm(
            $config,
            $data,
            [
                'enabledRole' => '',
                'roles'       => [
                    ['name' => 'role1'],
                    ['name' => 'role2']
                ]
            ]
        );
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertFalse($role1->isEnabled());
        self::assertFalse($role2->isEnabled());
    }

    public function testProcessWhenPrimaryFieldIsSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm($config, $data, ['enabledRole' => 'role1']);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertTrue($role1->isEnabled());
        self::assertFalse($role2->isEnabled());
    }

    public function testProcessWhenBothPrimaryFieldAndAssociationAreSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm(
            $config,
            $data,
            [
                'enabledRole' => 'role1',
                'roles'       => [
                    ['name' => 'role1'],
                    ['name' => 'role2']
                ]
            ]
        );
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertTrue($role1->isEnabled());
        self::assertFalse($role2->isEnabled());
    }

    public function testProcessWhenNewValueForPrimaryFieldIsSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $form = $this->processForm($config, $data, ['enabledRole' => 'role3']);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        $roles = $data->getRoles();
        self::assertCount(2, $roles);
        self::assertFalse($role1->isEnabled());
        self::assertTrue($role2->isEnabled());
        self::assertEquals('role3', $role2->getName());
    }

    public function testProcessWhenUnknownValueForPrimaryFieldIsSubmittedAndAssociationIsSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $this->addRole($data, 'role1', false);
        $this->addRole($data, 'role2', true);

        $form = $this->processForm(
            $config,
            $data,
            [
                'enabledRole' => 'unknown',
                'roles'       => [
                    ['name' => 'role1'],
                    ['name' => 'role2']
                ]
            ]
        );
        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        /** @var FormError[] $errors */
        $errors = $form->get('enabledRole')->getErrors();
        self::assertEquals(
            'Unknown enabled group.',
            $errors[0]->getMessage()
        );
    }

    public function testProcessWhenInvalidValueForPrimaryFieldIsSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $this->addRole($data, 'role1', false);
        $this->addRole($data, 'role2', true);

        $form = $this->processForm(
            $config,
            $data,
            ['enabledRole' => '1'],
            ['constraints' => [new Assert\Length(['min' => 3]), new Assert\NotBlank()]],
            RestrictedNameContainerType::class
        );
        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        /** @var FormError[] $errors */
        $errors = $form->get('enabledRole')->getErrors();
        self::assertEquals(
            'This value is too short. It should have 3 characters or more.',
            $errors[0]->getMessage()
        );
        self::assertCount(0, $form->get('roles')->getErrors(true));
    }

    public function testProcessWhenInvalidValueForPrimaryFieldIsSubmittedAndAssociationIsSubmitted(): void
    {
        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('roles');
        $rolesField->getOrCreateTargetEntity()->addField('name');

        $data = new Entity\Account();
        $this->addRole($data, 'role1', false);
        $this->addRole($data, 'role2', true);

        $form = $this->processForm(
            $config,
            $data,
            [
                'enabledRole' => '1',
                'roles'       => [
                    ['name' => 'role1'],
                    ['name' => 'role2']
                ]
            ],
            ['constraints' => [new Assert\Length(['min' => 3]), new Assert\NotBlank()]],
        );
        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->isValid());
        /** @var FormError[] $errors */
        $errors = $form->get('enabledRole')->getErrors();
        self::assertEquals(
            'Unknown enabled group.',
            $errors[0]->getMessage()
        );
    }

    public function testProcessForRenamedFields(): void
    {
        $this->processor = new MapPrimaryField(
            PropertyAccess::createPropertyAccessor(),
            'Unknown enabled group.',
            'enabledRole',
            'renamedRoles',
            'renamedName',
            'enabled'
        );

        $config = new EntityDefinitionConfig();
        $config->addField('enabledRole');
        $rolesField = $config->addField('renamedRoles');
        $rolesField->setPropertyPath('roles');
        $rolesField->getOrCreateTargetEntity()->addField('renamedName')->setPropertyPath('name');

        $data = new Entity\Account();
        $role1 = $this->addRole($data, 'role1', false);
        $role2 = $this->addRole($data, 'role2', true);

        $formBuilder = $this->getFormBuilder($config);
        $formBuilder->add('enabledRole', null, ['mapped' => false]);
        $formBuilder->add(
            'renamedRoles',
            CollectionType::class,
            [
                'property_path' => 'roles',
                'by_reference'  => false,
                'allow_add'     => true,
                'allow_delete'  => true,
                'entry_type'    => RenamedNameContainerType::class,
                'entry_options' => ['data_class' => Entity\Role::class]
            ]
        );

        $form = $formBuilder->getForm();
        $form->setData($data);
        $form->submit(['enabledRole' => 'role1'], false);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        self::assertTrue($role1->isEnabled());
        self::assertFalse($role2->isEnabled());
    }
}
