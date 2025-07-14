<?php

namespace Oro\Bundle\OrganizationBundle\Tests\Unit\Form\Extension;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Entity\Manager\BusinessUnitManager;
use Oro\Bundle\OrganizationBundle\Form\EventListener\OwnerFormSubscriber;
use Oro\Bundle\OrganizationBundle\Form\Extension\OwnerFormExtension;
use Oro\Bundle\OrganizationBundle\Form\Type\BusinessUnitSelectAutocomplete;
use Oro\Bundle\OrganizationBundle\Form\Type\OwnershipType;
use Oro\Bundle\OrganizationBundle\Tests\Unit\Fixture\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Tests\Unit\Form\Extension\Stub\OwnerFormExtensionStub;
use Oro\Bundle\SecurityBundle\Acl\Voter\AclVoter;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\SecurityBundle\Owner\EntityOwnerAccessor;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadata;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProviderInterface;
use Oro\Bundle\SecurityBundle\Owner\OwnerTreeProvider;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Form\Type\UserAclSelectType;
use Oro\Component\Testing\ReflectionUtil;
use Oro\Component\Testing\Unit\TestContainerBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class OwnerFormExtensionTest extends TestCase
{
    private ManagerRegistry&MockObject $doctrine;
    private OwnershipMetadataProviderInterface&MockObject $ownershipMetadataProvider;
    private BusinessUnitManager&MockObject $businessUnitManager;
    private AuthorizationCheckerInterface&MockObject $authorizationChecker;
    private TokenAccessorInterface&MockObject $tokenAccessor;
    private FormBuilder&MockObject $builder;
    private User&MockObject $user;
    private array $organizations;
    private string $fieldName;
    private string $fieldLabel;
    private string $entityClassName;
    private OwnerFormExtension $extension;
    private Organization $organization;
    private EntityOwnerAccessor&MockObject $entityOwnerAccessor;

    #[\Override]
    protected function setUp(): void
    {
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->ownershipMetadataProvider = $this->createMock(OwnershipMetadataProviderInterface::class);
        $this->businessUnitManager = $this->createMock(BusinessUnitManager::class);
        $this->businessUnitManager->expects($this->any())
            ->method('getBusinessUnitIds')
            ->willReturn([1, 2]);
        $organization = $this->createMock(\Oro\Bundle\OrganizationBundle\Entity\Organization::class);
        $this->organizations = [$organization];
        $businessUnit = $this->createMock(BusinessUnit::class);
        $businessUnit->expects($this->any())
            ->method('getOrganization')
            ->willReturn($organization);
        $this->user = $this->createMock(User::class);
        $this->user->expects($this->any())
            ->method('getId')
            ->willReturn(1);
        $this->user->expects($this->any())
            ->method('getBusinessUnits')
            ->willReturn(new ArrayCollection([$businessUnit]));
        $this->organization = new Organization();
        $this->organization->setId(1);
        $this->entityClassName = get_class($this->user);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $config = $this->createMock(FormConfigInterface::class);
        $config->expects($this->any())
            ->method('getCompound')
            ->willReturn(true);
        $config->expects($this->any())
            ->method('getDataClass')
            ->willReturn($this->entityClassName);
        $this->builder = $this->createMock(FormBuilder::class);
        $this->builder->expects($this->any())
            ->method('getFormConfig')
            ->willReturn($config);
        $this->builder->expects($this->any())
            ->method('getOption')
            ->with('required')
            ->willReturn(true);
        $this->fieldName = 'owner';
        $this->fieldLabel = 'oro.user.owner.label';

        $aclVoter = $this->createMock(AclVoter::class);

        $treeProvider = $this->createMock(OwnerTreeProvider::class);

        $this->entityOwnerAccessor = $this->createMock(EntityOwnerAccessor::class);

        $this->entityOwnerAccessor->expects($this->any())
            ->method('getOwner')
            ->willReturnCallback(function ($entity) {
                return $entity->getOwner();
            });

        $container = TestContainerBuilder::create()
            ->add('security.acl.voter.basic_permissions', $aclVoter)
            ->add('oro_security.owner.entity_owner_accessor', $this->entityOwnerAccessor)
            ->add('oro_security.ownership_tree_provider', $treeProvider)
            ->add('oro_organization.business_unit_manager', $this->businessUnitManager)
            ->getContainer($this);

        $this->extension = new OwnerFormExtension(
            $this->doctrine,
            $this->tokenAccessor,
            $this->authorizationChecker,
            $this->ownershipMetadataProvider,
            $container
        );
    }

    public function testNotCompoundForm(): void
    {
        $config = $this->createMock(FormConfigInterface::class);
        $config->expects($this->any())
            ->method('getCompound')
            ->willReturn(false);

        $this->builder = $this->createMock(FormBuilder::class);
        $this->builder->expects($this->any())
            ->method('getFormConfig')
            ->willReturn($config);
        $this->builder->expects($this->never())
            ->method('add');

        $this->ownershipMetadataProvider->expects($this->never())
            ->method('getMetadata');

        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    public function testAnonymousUser(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($this->createMock(UserInterface::class));

        $this->ownershipMetadataProvider->expects($this->never())
            ->method('getMetadata');
        $this->builder->expects($this->never())
            ->method('add');

        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    /**
     * Testing case with user owner type and change owner permission granted
     */
    public function testUserOwnerBuildFormGranted(): void
    {
        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));

        $this->mockConfigs(['is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_USER]);
        $this->builder->expects($this->once())
            ->method('add')
            ->with($this->fieldName, UserAclSelectType::class);
        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    /**
     * Testing case with user owner type and change owner permission isn't granted
     */
    public function testUserOwnerBuildFormNotGranted(): void
    {
        $this->mockConfigs(['is_granted' => false, 'owner_type' => OwnershipType::OWNER_TYPE_USER]);
        $this->builder->expects($this->never())
            ->method('add');
        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    /**
     * Testing case with business unit owner type and change owner permission granted
     */
    public function testBusinessUnitOwnerBuildFormGranted(): void
    {
        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));

        $this->mockConfigs(['is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_BUSINESS_UNIT]);

        $this->builder->expects($this->once())
            ->method('add')
            ->with(
                $this->fieldName,
                BusinessUnitSelectAutocomplete::class,
                [
                    'placeholder' => 'oro.business_unit.form.choose_business_user',
                    'label' => 'oro.user.owner.label',
                    'configs' => [
                        'multiple' => false,
                        'allowClear' => false,
                        'autocomplete_alias' => 'business_units_owner_search_handler',
                        'component' => 'tree-autocomplete'
                    ],
                    'required' => false,
                    'autocomplete_alias' => 'business_units_owner_search_handler'
                ]
            );
        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    /**
     * Testing case with business unit owner type and change owner permission granted, but view business unit not.
     */
    public function testBusinessUnitOwnerBuildFormAssignGrantedViewBusinessUnitNotGranted(): void
    {
        $this->tokenAccessor->expects($this->any())
            ->method('getOrganization')
            ->willReturn($this->organization);
        $this->tokenAccessor->expects($this->any())
            ->method('getOrganizationId')
            ->willReturn($this->organization->getId());
        $this->tokenAccessor->expects($this->any())
            ->method('getUser')
            ->willReturn($this->user);

        $this->authorizationChecker->expects($this->any())
            ->method('isGranted')
            ->withConsecutive(
                ['CREATE', 'entity:' . $this->entityClassName],
                ['VIEW', 'entity:' . BusinessUnit::class]
            )
            ->willReturnOnConsecutiveCalls(
                true,
                false
            );
        $metadata = new OwnershipMetadata(OwnershipType::OWNER_TYPE_BUSINESS_UNIT, 'owner', 'owner_id');
        $this->ownershipMetadataProvider->expects($this->any())
            ->method('getMetadata')
            ->with($this->entityClassName)
            ->willReturn($metadata);

        $aclVoter = $this->createMock(AclVoter::class);
        $treeProvider = $this->createMock(OwnerTreeProvider::class);
        $classMetadata = $this->createMock(ClassMetadataInfo::class);

        $classMetadata->expects($this->any())
            ->method('getSingleIdentifierFieldName')
            ->willReturn('name');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->any())
            ->method('getClassMetadata')
            ->willReturn($classMetadata);

        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($em);

        $container = TestContainerBuilder::create()
            ->add('security.acl.voter.basic_permissions', $aclVoter)
            ->add('oro_security.owner.entity_owner_accessor', $this->entityOwnerAccessor)
            ->add('oro_security.ownership_tree_provider', $treeProvider)
            ->add('oro_organization.business_unit_manager', $this->businessUnitManager)
            ->getContainer($this);

        $this->extension = new OwnerFormExtension(
            $this->doctrine,
            $this->tokenAccessor,
            $this->authorizationChecker,
            $this->ownershipMetadataProvider,
            $container
        );

        $this->builder->expects($this->any())
            ->method('get')
            ->with($this->fieldName)
            ->willReturn($this->builder);

        $this->builder->expects($this->once())
            ->method('add')
            ->with($this->fieldName, HiddenType::class);

        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    /**
     * Testing case with business unit owner type and change owner permission isn't granted
     */
    public function testBusinessUnitOwnerBuildFormNotGranted(): void
    {
        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));

        $this->mockConfigs(['is_granted' => false, 'owner_type' => OwnershipType::OWNER_TYPE_BUSINESS_UNIT]);
        $this->builder->expects($this->once())
            ->method('add')
            ->with(
                $this->fieldName,
                EntityType::class,
                [
                    'class' => BusinessUnit::class,
                    'choice_label' => 'name',
                    'mapped' => true,
                    'required' => true,
                    'constraints' => [new NotBlank()],
                    'label' => 'oro.user.owner.label',
                    'translatable_options' => false,
                    'query_builder' => function () {
                    },
                ]
            );
        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    /**
     * Testing case with organization owner type and change owner permission granted
     */
    public function testOrganizationOwnerBuildFormGranted(): void
    {
        $this->mockConfigs(['is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_ORGANIZATION]);
        $this->builder->expects($this->never())
            ->method('add');
        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    /**
     * Testing case with organization owner type and change owner permission isn't granted
     */
    public function testOrganizationOwnerBuildFormNotGranted(): void
    {
        $this->mockConfigs(['is_granted' => false, 'owner_type' => OwnershipType::OWNER_TYPE_ORGANIZATION]);
        $this->builder->expects($this->never())
            ->method('add');
        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    public function testEventListener(): void
    {
        $this->mockConfigs(['is_granted' => false, 'owner_type' => OwnershipType::OWNER_TYPE_ORGANIZATION]);
        $this->builder->expects($this->never())
            ->method('addEventSubscriber');
        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    /**
     * Test case, when business unit not assigned and not available for user
     */
    public function testDefaultOwnerUnavailableBusinessUnit(): void
    {
        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));

        $this->mockConfigs(['is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_BUSINESS_UNIT]);

        $businessUnit = $this->createMock(BusinessUnit::class);
        $this->user->expects($this->any())
            ->method('getOwner')
            ->willReturn($businessUnit);

        $isAssignGranted = true;
        $this->builder->expects($this->once())
            ->method('addEventSubscriber')
            ->with(
                new OwnerFormSubscriber(
                    $this->doctrine,
                    $this->fieldName,
                    $this->fieldLabel,
                    $isAssignGranted,
                    null
                )
            );

        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }

    private function mockConfigs(array $values): void
    {
        $this->tokenAccessor->expects($this->any())
            ->method('getOrganization')
            ->willReturn($this->organization);
        $this->tokenAccessor->expects($this->any())
            ->method('getOrganizationId')
            ->willReturn($this->organization->getId());
        $this->tokenAccessor->expects($this->any())
            ->method('getUser')
            ->willReturn($this->user);

        $this->authorizationChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn($values['is_granted']);
        $metadata = OwnershipType::OWNER_TYPE_NONE === $values['owner_type']
            ? new OwnershipMetadata($values['owner_type'])
            : new OwnershipMetadata($values['owner_type'], 'owner', 'owner_id');
        $this->ownershipMetadataProvider->expects($this->any())
            ->method('getMetadata')
            ->with($this->entityClassName)
            ->willReturn($metadata);

        $aclVoter = $this->createMock(AclVoter::class);

        $treeProvider = $this->createMock(OwnerTreeProvider::class);

        $container = TestContainerBuilder::create()
            ->add('security.acl.voter.basic_permissions', $aclVoter)
            ->add('oro_security.owner.entity_owner_accessor', $this->entityOwnerAccessor)
            ->add('oro_security.ownership_tree_provider', $treeProvider)
            ->add('oro_organization.business_unit_manager', $this->businessUnitManager)
            ->getContainer($this);

        $this->extension = new OwnerFormExtension(
            $this->doctrine,
            $this->tokenAccessor,
            $this->authorizationChecker,
            $this->ownershipMetadataProvider,
            $container
        );
    }

    public function testPreSubmit(): void
    {
        $this->doctrine->expects($this->atLeastOnce())
            ->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));

        $this->mockConfigs(
            [
                'is_granted' => true,
                'owner_type' => OwnershipType::OWNER_TYPE_USER
            ]
        );
        $businessUnit = $this->createMock(BusinessUnit::class);
        $businessUnit->expects($this->any())
            ->method('getId')
            ->willReturn(2);
        $this->user->expects($this->any())
            ->method('getId')
            ->willReturn(1);
        $newUser = $this->createMock(User::class);
        $newUser->expects($this->any())
            ->method('getId')
            ->willReturn(2);
        $this->user->expects($this->any())
            ->method('getOwner')
            ->willReturn($newUser);
        $ownerForm = $this->createMock(Form::class);
        $form = $this->createMock(Form::class);
        $form->expects($this->any())
            ->method('get')
            ->willReturn($ownerForm);
        $ownerForm->expects($this->any())
            ->method('getData')
            ->willReturn($this->user);
        $form->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $form->expects($this->any())
            ->method('getParent')
            ->willReturn(null);
        $form->expects($this->once())
            ->method('getNormData')
            ->willReturn($this->entityClassName);
        $this->businessUnitManager->expects($this->once())
            ->method('canUserBeSetAsOwner')
            ->willReturn(false);

        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);

        $event = new FormEvent($form, $this->user);

        $this->extension->preSubmit($event);

        $ownerForm->expects($this->once())
            ->method('addError')
            ->with(new FormError('You have no permission to set this owner'));

        $this->extension->postSubmit($event);
    }

    public function testPreSetData(): void
    {
        $this->doctrine->expects($this->atLeastOnce())
            ->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));

        $this->mockConfigs(['is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_USER]);
        $form = $this->createMock(Form::class);
        $form->expects($this->any())
            ->method('getConfig')
            ->willReturn($this->builder);
        $form->expects($this->any())
            ->method('getParent')
            ->willReturn(null);
        $form->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $this->user->expects($this->any())
            ->method('getId')
            ->willReturn(1);
        $businessUnit = $this->createMock(BusinessUnit::class);
        $this->user->expects($this->any())
            ->method('getOwner')
            ->willReturn($businessUnit);
        $form->expects($this->once())
            ->method('remove');

        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);

        $event = new FormEvent($form, $this->user);
        $this->extension->preSetData($event);
    }

    /**
     * The test case, when default owner set from User's owner
     */
    public function testDefaultOwnerAvailableBusinessUnit(): void
    {
        $this->doctrine->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($this->createMock(EntityManagerInterface::class));

        $this->mockConfigs(['is_granted' => true, 'owner_type' => OwnershipType::OWNER_TYPE_BUSINESS_UNIT]);

        $organization = new Organization();
        $organization->setId(1);
        $businessUnit = new BusinessUnit();
        ReflectionUtil::setId($businessUnit, 1);
        $businessUnit->setOrganization($organization);
        $this->user->expects($this->any())
            ->method('getOwner')
            ->willReturn($businessUnit);

        $isAssignGranted = true;
        $this->builder->expects($this->once())
            ->method('addEventSubscriber')
            ->with(
                new OwnerFormSubscriber(
                    $this->doctrine,
                    $this->fieldName,
                    $this->fieldLabel,
                    $isAssignGranted,
                    $businessUnit
                )
            );

        $aclVoter = $this->createMock(AclVoter::class);
        $treeProvider = $this->createMock(OwnerTreeProvider::class);

        $container = TestContainerBuilder::create()
            ->add('security.acl.voter.basic_permissions', $aclVoter)
            ->add('oro_security.owner.entity_owner_accessor', $this->entityOwnerAccessor)
            ->add('oro_security.ownership_tree_provider', $treeProvider)
            ->add('oro_organization.business_unit_manager', $this->businessUnitManager)
            ->getContainer($this);

        $this->extension = new OwnerFormExtensionStub(
            $this->doctrine,
            $this->tokenAccessor,
            $this->authorizationChecker,
            $this->ownershipMetadataProvider,
            $container
        );

        $this->extension->buildForm($this->builder, ['ownership_disabled' => false]);
    }
}
