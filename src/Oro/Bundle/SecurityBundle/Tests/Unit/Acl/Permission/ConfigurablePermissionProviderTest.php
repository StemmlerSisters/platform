<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Acl\Permission;

use Oro\Bundle\SecurityBundle\Acl\Permission\ConfigurablePermissionProvider;
use Oro\Bundle\SecurityBundle\Configuration\ConfigurablePermissionConfigurationProvider;
use Oro\Bundle\SecurityBundle\Model\ConfigurablePermission;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurablePermissionProviderTest extends TestCase
{
    private ConfigurablePermissionConfigurationProvider&MockObject $configurationProvider;
    private ConfigurablePermissionProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->configurationProvider = $this->createMock(ConfigurablePermissionConfigurationProvider::class);

        $this->provider = new ConfigurablePermissionProvider($this->configurationProvider);
    }

    /**
     * @dataProvider getDataProvider
     */
    public function testGet(string $name, array $data, ConfigurablePermission $expected): void
    {
        $this->configurationProvider->expects($this->once())
            ->method('getConfiguration')
            ->willReturn($data);

        $this->assertEquals($expected, $this->provider->get($name));
    }

    public function getDataProvider(): array
    {
        $name = 'test_name';
        $default = true;
        $entities = ['entity1', 'entity2'];
        $capabilities = ['capability1', 'capability2'];
        $data = [
            $name => [
                'default' => $default,
                'entities' => $entities,
                'capabilities' => $capabilities,
            ]
        ];

        return [
            'empty data' => [
                'name' => $name,
                'data' => [],
                'expected' => new ConfigurablePermission($name),
            ],
            'contains' => [
                'name' => $name,
                'data' => $data,
                'expected' => new ConfigurablePermission($name, $default, $entities, $capabilities),
            ],
            'not contains' => [
                'name' => 'some_other_name',
                'data' => $data,
                'expected' => new ConfigurablePermission('some_other_name'),
            ],
        ];
    }
}
