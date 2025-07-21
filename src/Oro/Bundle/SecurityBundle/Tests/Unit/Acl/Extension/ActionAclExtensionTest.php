<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Extension;

use Oro\Bundle\SecurityBundle\Acl\Extension\ActionAclExtension;
use Oro\Bundle\SecurityBundle\Acl\Extension\ActionMaskBuilder;
use Oro\Bundle\SecurityBundle\Attribute\Acl as AclAttribute;
use Oro\Bundle\SecurityBundle\Metadata\ActionSecurityMetadataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;

class ActionAclExtensionTest extends TestCase
{
    private ActionSecurityMetadataProvider&MockObject $metadataProvider;
    private ActionAclExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->metadataProvider = $this->createMock(ActionSecurityMetadataProvider::class);

        $this->extension = new ActionAclExtension($this->metadataProvider);
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupports(string $id, string $type, string $action, bool $isKnownAction, bool $expected): void
    {
        $this->metadataProvider->expects($isKnownAction ? $this->once() : $this->never())
            ->method('isKnownAction')
            ->with($action)
            ->willReturn($isKnownAction);

        $this->assertEquals($expected, $this->extension->supports($type, $id));
    }

    public function supportsDataProvider(): array
    {
        return [
            [
                'id' => 'entity',
                'type' => \stdClass::class,
                'action' => \stdClass::class,
                'isKnownAction' => false,
                'expected' => false
            ],
            [
                'id' => 'action',
                'type' => 'action_id',
                'action' => 'action_id',
                'isKnownAction' => true,
                'expected' => true
            ],
            [
                'id' => 'action',
                'type' => '@action_id',
                'action' => 'action_id',
                'isKnownAction' => true,
                'expected' => true
            ],
            [
                'id' => 'action',
                'type' => 'group@action_id',
                'action' => 'action_id',
                'isKnownAction' => true,
                'expected' => true
            ]
        ];
    }

    /**
     * @dataProvider getObjectIdentityDataProvider
     */
    public function testGetObjectIdentity(mixed $val, ObjectIdentity $expected): void
    {
        $this->assertEquals($expected, $this->extension->getObjectIdentity($val));
    }

    public function getObjectIdentityDataProvider(): array
    {
        $attribute = AclAttribute::fromArray([
            'id' => 'action_id',
            'type' => 'action'
        ]);

        $attribute2 = AclAttribute::fromArray([
            'id' => 'action_id',
            'type' => 'action',
            'group_name' => 'group'
        ]);

        return [
            [
                'val' => 'action:action_id',
                'expected' => new ObjectIdentity('action', 'action_id')
            ],
            [
                'val' => 'action:group@action_id',
                'expected' => new ObjectIdentity('action', 'group@action_id')
            ],
            [
                'val' => 'action:@action_id',
                'expected' => new ObjectIdentity('action', 'action_id')
            ],
            [
                'val' => $attribute,
                'expected' => new ObjectIdentity('action', 'action_id')
            ],
            [
                'val' => $attribute2,
                'expected' => new ObjectIdentity('action', 'group@action_id')
            ]
        ];
    }

    public function testGetDefaultPermission(): void
    {
        self::assertEquals('EXECUTE', $this->extension->getDefaultPermission());
    }

    public function testGetPermissionGroupMask(): void
    {
        self::assertNull($this->extension->getPermissionGroupMask(1));
    }

    /**
     * @dataProvider getPermissionsProvider
     */
    public function testGetPermissions(?int $mask, bool $setOnly, bool $byCurrentGroup, array $expected): void
    {
        $this->assertEquals($expected, $this->extension->getPermissions($mask, $setOnly, $byCurrentGroup));
    }

    public function getPermissionsProvider(): array
    {
        return [
            'mask = 0 and setOnly' => [
                'mask' => 0,
                'setOnly' => true,
                'byCurrentGroup' => false,
                'expected' => [],
            ],
            'null mask and setOnly' => [
                'mask' => null,
                'setOnly' => true,
                'byCurrentGroup' => false,
                'expected' => ['EXECUTE'],
            ],
            'mask = 0 and not setOnly' => [
                'mask' => 0,
                'setOnly' => false,
                'byCurrentGroup' => false,
                'expected' => ['EXECUTE'],
            ],
            'mask = 1 and setOnly' => [
                'mask' => 1,
                'setOnly' => true,
                'byCurrentGroup' => false,
                'expected' => ['EXECUTE'],
            ],
            'mask = 0 and setOnly and byCurrentGroup' => [
                'mask' => 0,
                'setOnly' => true,
                'byCurrentGroup' => true,
                'expected' => [],
            ],
        ];
    }

    /**
     * @dataProvider getServiceBitsProvider
     */
    public function testGetServiceBits(int $mask, int $expectedMask): void
    {
        self::assertEquals($expectedMask, $this->extension->getServiceBits($mask));
    }

    public function getServiceBitsProvider(): array
    {
        return [
            'zero mask'                        => [
                ActionMaskBuilder::GROUP_NONE,
                ActionMaskBuilder::GROUP_NONE
            ],
            'not zero mask'                    => [
                ActionMaskBuilder::MASK_EXECUTE,
                ActionMaskBuilder::GROUP_NONE
            ],
            'zero mask, not zero identity'     => [
                ActionMaskBuilder::REMOVE_SERVICE_BITS + 1,
                ActionMaskBuilder::REMOVE_SERVICE_BITS + 1
            ],
            'not zero mask, not zero identity' => [
                (ActionMaskBuilder::REMOVE_SERVICE_BITS + 1) | ActionMaskBuilder::MASK_EXECUTE,
                ActionMaskBuilder::REMOVE_SERVICE_BITS + 1
            ],
        ];
    }

    /**
     * @dataProvider removeServiceBitsProvider
     */
    public function testRemoveServiceBits(int $mask, int $expectedMask): void
    {
        self::assertEquals($expectedMask, $this->extension->removeServiceBits($mask));
    }

    public function removeServiceBitsProvider(): array
    {
        return [
            'zero mask'                        => [
                ActionMaskBuilder::GROUP_NONE,
                ActionMaskBuilder::GROUP_NONE
            ],
            'not zero mask'                    => [
                ActionMaskBuilder::MASK_EXECUTE,
                ActionMaskBuilder::MASK_EXECUTE
            ],
            'zero mask, not zero identity'     => [
                ActionMaskBuilder::REMOVE_SERVICE_BITS + 1,
                ActionMaskBuilder::GROUP_NONE
            ],
            'not zero mask, not zero identity' => [
                (ActionMaskBuilder::REMOVE_SERVICE_BITS + 1) | ActionMaskBuilder::MASK_EXECUTE,
                ActionMaskBuilder::MASK_EXECUTE
            ],
        ];
    }
}
