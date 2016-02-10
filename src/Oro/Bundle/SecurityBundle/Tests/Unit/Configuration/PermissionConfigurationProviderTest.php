<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\Configuration;

use Oro\Bundle\SecurityBundle\Configuration\PermissionConfiguration;
use Oro\Bundle\SecurityBundle\Configuration\PermissionConfigurationProvider;
use Oro\Bundle\SecurityBundle\Configuration\PermissionListConfiguration;
use Oro\Bundle\SecurityBundle\Tests\Unit\Configuration\Stub\TestBundle1\TestBundle1;
use Oro\Bundle\SecurityBundle\Tests\Unit\Configuration\Stub\TestBundle2\TestBundle2;
use Oro\Component\Config\CumulativeResourceManager;

class PermissionConfigurationProviderTest extends \PHPUnit_Framework_TestCase
{
    const PERMISSION1 = 'PERMISSION1';

    const PERMISSION2 = 'PERMISSION2';

    const PERMISSION3 = 'PERMISSION3';

    private $permissions = [
        self::PERMISSION1 => [
            'label' => 'Label for Permission 1',
            'group_names' => ['frontend'],
            'apply_to_all' => true,
            'apply_to_entities' => [],
            'exclude_entities' => [],
        ],
        self::PERMISSION2 => [
            'label' => 'Label for Permission 2',
            'group_names' => [PermissionConfiguration::DEFAULT_GROUP_NAME, 'frontend', 'new_group'],
            'apply_to_all' => false,
            'apply_to_entities' => [
                'OroTestFrameworkBundle:TestActivity',
                'OroTestFrameworkBundle:Product',
                'OroTestFrameworkBundle:TestActivityTarget',
            ],
            'exclude_entities' => [
                'OroTestFrameworkBundle:Item',
                'OroTestFrameworkBundle:ItemValue',
                'OroTestFrameworkBundle:WorkflowAwareEntity',
            ],
            'description' => 'Permission 2 description',
        ],

        self::PERMISSION3 => [
            'label' => 'Label for Permission 3',
            'group_names' => ['default'],
            'apply_to_all' => true,
            'apply_to_entities' => ['NotManageableEntity'],
            'exclude_entities' => [],
        ],
    ];

    /**
     * @var PermissionConfigurationProvider
     */
    protected $provider;

    protected function setUp()
    {
        $bundle1  = new TestBundle1();
        $bundle2  = new TestBundle2();
        $bundles = [
            $bundle1->getName() => get_class($bundle1),
            $bundle2->getName() => get_class($bundle2),
        ];
        CumulativeResourceManager::getInstance()
            ->clear()
            ->setBundles($bundles);
        $this->provider = new PermissionConfigurationProvider(
            new PermissionListConfiguration(new PermissionConfiguration()),
            $bundles
        );
    }

    protected function tearDown()
    {
        unset($this->provider);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testIncorrectConfiguration()
    {
        $this->loadConfig('permissionsIncorrect.yml');
        $this->provider->getPermissionConfiguration();
    }

    public function testCorrectConfiguration()
    {
        $expectedPermissions = [
            self::PERMISSION1 => $this->permissions[self::PERMISSION1],
            self::PERMISSION2 => $this->permissions[self::PERMISSION2],
            self::PERMISSION3 => $this->permissions[self::PERMISSION3],
        ];

        $this->loadConfig('permissionsCorrect.yml');
        $permissions = $this->provider->getPermissionConfiguration();
        $this->assertEquals($expectedPermissions, $permissions);
    }

    public function testFilterPermissionsConfiguration()
    {
        $expectedPermissions = [
            self::PERMISSION1 => $this->permissions[self::PERMISSION1],
            self::PERMISSION3 => $this->permissions[self::PERMISSION3],
        ];

        $this->loadConfig('permissionsCorrect.yml');
        $permissions = $this->provider->getPermissionConfiguration(array_keys($expectedPermissions));
        $this->assertEquals($expectedPermissions, $permissions);
    }

    /**
     * @param string $path
     */
    protected function loadConfig($path)
    {
        $reflection = new \ReflectionClass('Oro\Bundle\SecurityBundle\Configuration\PermissionConfigurationProvider');
        $reflectionProperty = $reflection->getProperty('configPath');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->provider, $path);
    }
}
