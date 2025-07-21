<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Metadata;

use Oro\Bundle\SecurityBundle\Attribute\Acl as AclAttribute;
use Oro\Bundle\SecurityBundle\Metadata\AclAttributeProvider;
use Oro\Bundle\SecurityBundle\Metadata\ActionSecurityMetadata;
use Oro\Bundle\SecurityBundle\Metadata\ActionSecurityMetadataProvider;
use Oro\Bundle\SecurityBundle\Metadata\Label;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ActionSecurityMetadataProviderTest extends TestCase
{
    private AclAttributeProvider&MockObject $attributeProvider;
    private ActionSecurityMetadataProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->attributeProvider = $this->createMock(AclAttributeProvider::class);

        $this->provider = new ActionSecurityMetadataProvider($this->attributeProvider);
    }

    public function testIsKnownActionForKnownAction(): void
    {
        $this->attributeProvider->expects($this->once())
            ->method('findAttributeById')
            ->with('SomeAction')
            ->willReturn(AclAttribute::fromArray(['id' => 'SomeAction', 'type' => 'action']));

        $this->assertTrue($this->provider->isKnownAction('SomeAction'));
    }

    public function testIsKnownActionForNotActionAclAttributeId(): void
    {
        $this->attributeProvider->expects($this->once())
            ->method('findAttributeById')
            ->with('SomeAclAttributeId')
            ->willReturn(AclAttribute::fromArray(['id' => 'SomeAclAttributeId', 'type' => 'entity']));

        $this->assertFalse($this->provider->isKnownAction('SomeAclAttributeId'));
    }

    public function testIsKnownActionForUnknownAction(): void
    {
        $this->attributeProvider->expects($this->once())
            ->method('findAttributeById')
            ->with('UnknownAction')
            ->willReturn(null);

        $this->assertFalse($this->provider->isKnownAction('UnknownAction'));
    }

    public function testGetActions(): void
    {
        $this->attributeProvider->expects($this->once())
            ->method('getAttributes')
            ->with('action')
            ->willReturn([
                AclAttribute::fromArray([
                    'id'          => 'test',
                    'type'        => 'action',
                    'group_name'  => 'TestGroup',
                    'label'       => 'TestLabel',
                    'description' => 'TestDescription',
                    'category'    => 'TestCategory'
                ])
            ]);

        $action = new ActionSecurityMetadata(
            'test',
            'TestGroup',
            new Label('TestLabel'),
            new Label('TestDescription'),
            'TestCategory'
        );

        $actions = $this->provider->getActions();
        $this->assertCount(1, $actions);
        $this->assertEquals($action, $actions[0]);

        // call with local cache
        $actions = $this->provider->getActions();
        $this->assertCount(1, $actions);
        $this->assertEquals($action, $actions[0]);
    }
}
