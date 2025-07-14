<?php

namespace Oro\Bundle\OrganizationBundle\Tests\Unit\Entity\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OrganizationBundle\Entity\Manager\BusinessUnitManager;
use Oro\Bundle\OrganizationBundle\Entity\Repository\BusinessUnitRepository;
use Oro\Bundle\OrganizationBundle\Tests\Unit\Fixture\Entity\BusinessUnit;
use Oro\Bundle\OrganizationBundle\Tests\Unit\Fixture\Entity\Organization;
use Oro\Bundle\OrganizationBundle\Tests\Unit\Fixture\Entity\User;
use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Bundle\SecurityBundle\Owner\OwnerTree;
use Oro\Bundle\SecurityBundle\Owner\OwnerTreeProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BusinessUnitManagerTest extends TestCase
{
    private TokenAccessorInterface&MockObject $tokenAccessor;
    private AclHelper&MockObject $aclHelper;
    private BusinessUnitRepository&MockObject $buRepo;
    private EntityRepository&MockObject $userRepo;
    private BusinessUnitManager $businessUnitManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->aclHelper = $this->createMock(AclHelper::class);
        $this->buRepo = $this->createMock(BusinessUnitRepository::class);
        $this->userRepo = $this->createMock(EntityRepository::class);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->any())
            ->method('getRepository')
            ->willReturnMap([
                [\Oro\Bundle\OrganizationBundle\Entity\BusinessUnit::class, null, $this->buRepo],
                [\Oro\Bundle\UserBundle\Entity\User::class, null, $this->userRepo],
            ]);

        $this->businessUnitManager = new BusinessUnitManager(
            $doctrine,
            $this->tokenAccessor,
            $this->aclHelper
        );
    }

    public function testGetTreeOptions(): void
    {
        $inputData = [
            [
                'id' => '3',
                'name' => 'First BU',
                'parent' => null,
                'organization' => 1,
                'children' => [
                    [
                        'id' => '5',
                        'name' => 'Sub First BU',
                        'parent' => null,
                        'organization' => 1,
                        'children' => [
                            [
                                'id' => '4',
                                'name' => 'Sub Sub First BU',
                                'parent' => null,
                                'organization' => 1,
                            ]
                        ]
                    ]
                ]
            ],
            [
                'id' => '10',
                'name' => 'Second BU',
                'parent' => null,
                'organization' => 1,
                'children' => [
                    [
                        'id' => 11,
                        'name' => 'Sub Second BU',
                        'parent' => null,
                        'organization' => 1,
                    ]
                ],
            ],
            [
                'id' => '15',
                'name' => 'BU wo children',
                'parent' => null,
                'organization' => 1,
            ]
        ];
        $result = $this->businessUnitManager->getTreeOptions($inputData);
        $expectedResult = [
            'First BU' => '3',
            '&nbsp;&nbsp;&nbsp;Sub First BU' => '5',
            '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Sub Sub First BU' => '4',
            'Second BU' => '10',
            '&nbsp;&nbsp;&nbsp;Sub Second BU' => '11',
            'BU wo children' => '15',
        ];
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @dataProvider getTreeNodesProvider
     */
    public function testGetTreeNodesCount(array $tree, int $expectedCount): void
    {
        $this->assertEquals($expectedCount, $this->businessUnitManager->getTreeNodesCount($tree));
    }

    public function getTreeNodesProvider(): array
    {
        return [
            [
                [],
                0,
            ],
            [
                [
                    [
                        'id' => 1,
                        'name' => 'org',
                        'children' => [
                            [
                                'id' => '3',
                                'name' => 'First BU',
                                'parent' => null,
                                'organization' => 1,
                                'children' => [
                                    [
                                        'id' => '5',
                                        'name' => 'Sub First BU',
                                        'parent' => null,
                                        'organization' => 1,
                                        'children' => [
                                            [
                                                'id' => '4',
                                                'name' => 'Sub Sub First BU',
                                                'parent' => null,
                                                'organization' => 1,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                4,
            ],
            [
                [
                    [
                        'id' => 1,
                        'name' => 'org',
                        'children' => [
                            [
                                'id' => '3',
                                'name' => 'First BU',
                                'parent' => null,
                                'organization' => 1,
                                'children' => [
                                    [
                                        'id' => '5',
                                        'name' => 'Sub First BU',
                                        'parent' => null,
                                        'organization' => 1,
                                        'children' => [
                                            [
                                                'id' => '4',
                                                'name' => 'Sub Sub First BU',
                                                'parent' => null,
                                                'organization' => 1,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'id' => '10',
                                'name' => 'Second BU',
                                'parent' => null,
                                'organization' => 1,
                                'children' => [
                                    [
                                        'id' => 11,
                                        'name' => 'Sub Second BU',
                                        'parent' => null,
                                        'organization' => 1,
                                    ],
                                ],
                            ],
                            [
                                'id' => '15',
                                'name' => 'BU wo children',
                                'parent' => null,
                                'organization' => 1,
                            ],
                        ],
                    ],
                ],
                7,
            ],
        ];
    }

    public function testGetBusinessUnitsTree(): void
    {
        $this->buRepo->expects($this->once())
            ->method('getBusinessUnitsTree');
        $this->businessUnitManager->getBusinessUnitsTree();
    }

    public function testGetBusinessUnitIds(): void
    {
        $this->buRepo->expects($this->once())
            ->method('getBusinessUnitIds');
        $this->businessUnitManager->getBusinessUnitIds();
    }

    public function testGetBusinessUnit(): void
    {
        $this->buRepo->expects($this->once())
            ->method('findOneBy');
        $this->businessUnitManager->getBusinessUnit();
    }

    /**
     * @dataProvider canUserBeSetAsOwnerDataProvider
     */
    public function testCanUserBeSetAsOwner(
        User $currentUser,
        User $newUser,
        int $accessLevel,
        Organization $organization,
        bool $isCanBeSet
    ): void {
        $tree = new OwnerTree();
        $this->addUserInfoToTree($tree, $currentUser);
        $this->addUserInfoToTree($tree, $newUser);

        $treeProvider = $this->createMock(OwnerTreeProvider::class);
        $treeProvider->expects($this->any())
            ->method('getTree')
            ->willReturn($tree);

        $result = $this->businessUnitManager->canUserBeSetAsOwner(
            $currentUser,
            $newUser,
            $accessLevel,
            $treeProvider,
            $organization
        );
        $this->assertEquals($isCanBeSet, $result);
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function canUserBeSetAsOwnerDataProvider(): array
    {
        $organization1 = new Organization();
        $organization1->setId(1);

        $organization2 = new Organization();
        $organization2->setId(2);

        $bu11 = new BusinessUnit();
        $bu11->setId(1);
        $bu11->setOrganization($organization1);

        $bu22 = new BusinessUnit();
        $bu22->setId(2);
        $bu22->setOrganization($organization2);

        $newUser = new User();
        $newUser->setId(2);
        $newUser->setOrganizations(new ArrayCollection([$organization1]));
        $newUser->setBusinessUnits(new ArrayCollection([$bu11]));

        return [
            'BASIC_LEVEL access level, current user' => [
                $this->getCurrentUser(2, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::BASIC_LEVEL,
                $organization1,
                true
            ],
            'BASIC_LEVEL access level, another user' => [
                $this->getCurrentUser(1, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::BASIC_LEVEL,
                $organization1,
                false
            ],
            'SYSTEM_LEVEL access level, current user' => [
                $this->getCurrentUser(2, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::SYSTEM_LEVEL,
                $organization1,
                true
            ],
            'SYSTEM_LEVEL access level, another user' => [
                $this->getCurrentUser(1, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::SYSTEM_LEVEL,
                $organization1,
                true
            ],
            'GLOBAL_LEVEL access level, current user' => [
                $this->getCurrentUser(2, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::GLOBAL_LEVEL,
                $organization1,
                true
            ],
            'GLOBAL_LEVEL access level, another user, same org' => [
                $this->getCurrentUser(1, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::GLOBAL_LEVEL,
                $organization1,
                true
            ],
            'GLOBAL_LEVEL access level, another user, different org' => [
                $this->getCurrentUser(1, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::GLOBAL_LEVEL,
                $organization2,
                false
            ],
            'LOCAL_LEVEL access level, current user' => [
                $this->getCurrentUser(2, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::LOCAL_LEVEL,
                $organization1,
                true
            ],
            'LOCAL_LEVEL access level, another user, same org' => [
                $this->getCurrentUser(1, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::LOCAL_LEVEL,
                $organization1,
                true
            ],
            'LOCAL_LEVEL access level, another user, different org' => [
                $this->getCurrentUser(1, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::LOCAL_LEVEL,
                $organization2,
                false
            ],
            'DEEP_LEVEL access level, current user' => [
                $this->getCurrentUser(2, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::DEEP_LEVEL,
                $organization1,
                true
            ],
            'DEEP_LEVEL access level, another user, same org' => [
                $this->getCurrentUser(1, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::DEEP_LEVEL,
                $organization1,
                true
            ],
            'DEEP_LEVEL access level, another user, different org' => [
                $this->getCurrentUser(1, [$organization1], [$bu11]),
                $newUser,
                AccessLevel::DEEP_LEVEL,
                $organization2,
                false
            ],
        ];
    }

    /**
     * @dataProvider canBusinessUnitBeSetAsOwnerDataProvider
     */
    public function testCanBusinessUnitBeSetAsOwner(
        User $currentUser,
        BusinessUnit $newBusinessUnit,
        int $accessLevel,
        Organization $organization,
        bool $isCanBeSet
    ): void {
        $tree = new OwnerTree();
        $this->addUserInfoToTree($tree, $currentUser);
        $this->addBusinessUnitInfoToTree($tree, $newBusinessUnit);

        $treeProvider = $this->createMock(OwnerTreeProvider::class);
        $treeProvider->expects($this->any())
            ->method('getTree')
            ->willReturn($tree);

        $this->buRepo->expects($this->any())
            ->method('getBusinessUnitIds')
            ->willReturn([1, 2]);

        $this->assertEquals(
            $isCanBeSet,
            $this->businessUnitManager->canBusinessUnitBeSetAsOwner(
                $currentUser,
                $newBusinessUnit,
                $accessLevel,
                $treeProvider,
                $organization
            )
        );
    }

    /**
     * @Suppress2Warnings(PHPMD.ExcessiveMethodLength)
     */
    public function canBusinessUnitBeSetAsOwnerDataProvider(): array
    {
        $organization1 = new Organization();
        $organization1->setId(1);

        $organization2 = new Organization();
        $organization2->setId(2);

        $bu11 = new BusinessUnit();
        $bu11->setId(1);
        $bu11->setOrganization($organization1);

        $bu22 = new BusinessUnit();
        $bu22->setId(2);
        $bu22->setOrganization($organization2);

        $newBusinessUnit = new BusinessUnit();
        $newBusinessUnit->setId(1);
        $newBusinessUnit->setOrganization($organization1);

        return [
            'BASIC_LEVEL access level, current business unit' => [
                $this->getCurrentUser(42, [$organization1], [$bu11]),
                $newBusinessUnit,
                AccessLevel::BASIC_LEVEL,
                $organization1,
                false
            ],
            'BASIC_LEVEL access level, another business unit' => [
                $this->getCurrentUser(42, [$organization2], [$bu22]),
                $newBusinessUnit,
                AccessLevel::BASIC_LEVEL,
                $organization2,
                false
            ],
            'SYSTEM_LEVEL access level, current business unit' => [
                $this->getCurrentUser(42, [$organization1], [$bu11]),
                $newBusinessUnit,
                AccessLevel::SYSTEM_LEVEL,
                $organization1,
                true
            ],
            'SYSTEM_LEVEL access level, another business unit' => [
                $this->getCurrentUser(42, [$organization2], [$bu22]),
                $newBusinessUnit,
                AccessLevel::SYSTEM_LEVEL,
                $organization2,
                true
            ],
            'GLOBAL_LEVEL access level, current business unit' => [
                $this->getCurrentUser(42, [$organization1], [$bu11]),
                $newBusinessUnit,
                AccessLevel::GLOBAL_LEVEL,
                $organization1,
                true
            ],
            'GLOBAL_LEVEL access level, another business unit' => [
                $this->getCurrentUser(42, [$organization2], [$bu22]),
                $newBusinessUnit,
                AccessLevel::GLOBAL_LEVEL,
                $organization2,
                true
            ],
            'LOCAL_LEVEL access level, current business unit' => [
                $this->getCurrentUser(42, [$organization1], [$bu11]),
                $newBusinessUnit,
                AccessLevel::LOCAL_LEVEL,
                $organization1,
                true
            ],
            'LOCAL_LEVEL access level, another business unit' => [
                $this->getCurrentUser(42, [$organization2], [$bu22]),
                $newBusinessUnit,
                AccessLevel::LOCAL_LEVEL,
                $organization2,
                false
            ],
            'DEEP_LEVEL access level, current business unit' => [
                $this->getCurrentUser(42, [$organization1], [$bu11]),
                $newBusinessUnit,
                AccessLevel::DEEP_LEVEL,
                $organization1,
                true
            ],
            'DEEP_LEVEL access level, another business unit' => [
                $this->getCurrentUser(42, [$organization2], [$bu22]),
                $newBusinessUnit,
                AccessLevel::DEEP_LEVEL,
                $organization2,
                false
            ]
        ];
    }

    private function getCurrentUser(int $id, array $organizations, array $bUnits): User
    {
        $user = new User();
        $user->setId($id);
        $user->setBusinessUnits(new ArrayCollection($bUnits));
        $user->setOrganizations(new ArrayCollection($organizations));

        return $user;
    }

    private function addUserInfoToTree(OwnerTree $tree, User $user): void
    {
        $owner = $user->getOwner();
        $tree->addUser($user->getId(), $owner?->getId());
        foreach ($user->getOrganizations() as $organization) {
            $tree->addUserOrganization($user->getId(), $organization->getId());
            foreach ($user->getBusinessUnits() as $businessUnit) {
                $organizationId = $organization->getId();
                $buOrganizationId = $businessUnit->getOrganization()->getId();
                if ($organizationId == $buOrganizationId) {
                    $tree->addUserBusinessUnit($user->getId(), $organizationId, $businessUnit->getId());
                }
            }
        }
    }

    private function addBusinessUnitInfoToTree(OwnerTree $tree, BusinessUnit $businessUnit): void
    {
        $owner = $businessUnit->getOwner();

        $tree->addBusinessUnit($businessUnit->getId(), $owner?->getId());
    }
}
