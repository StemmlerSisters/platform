<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Tools\ConfigFilter;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Tools\ConfigFilter\ByInitialStateFilter;
use PHPUnit\Framework\TestCase;

class ByInitialStateFilterTest extends TestCase
{
    /**
     * @dataProvider applyDataProvider
     */
    public function testApply(ConfigInterface $config, $expectedResult): void
    {
        $filter = new ByInitialStateFilter(
            [
                'entities' => [
                    'Test\ActiveEntity'        => ExtendScope::STATE_ACTIVE,
                    'Test\NewEntity'           => ExtendScope::STATE_NEW,
                    'Test\RequireUpdateEntity' => ExtendScope::STATE_UPDATE,
                    'Test\ToDeleteEntity'      => ExtendScope::STATE_DELETE,
                ],
                'fields'   => [
                    'Test\Entity' => [
                        'active_field'         => ExtendScope::STATE_ACTIVE,
                        'new_field'            => ExtendScope::STATE_NEW,
                        'require_update_field' => ExtendScope::STATE_UPDATE,
                        'to_delete_field'      => ExtendScope::STATE_DELETE,
                    ]
                ],
            ]
        );
        $this->assertEquals($expectedResult, $filter($config));
    }

    public function applyDataProvider(): array
    {
        return [
            'active entity'                                              => [
                $this->getEntityConfig(
                    'Test\ActiveEntity',
                    ['state' => ExtendScope::STATE_ACTIVE]
                ),
                true
            ],
            'active field'                                               => [
                $this->getFieldConfig(
                    'Test\ActiveEntity',
                    'active_field',
                    ['state' => ExtendScope::STATE_ACTIVE]
                ),
                true
            ],
            'created, but not committed entity'                          => [
                $this->getEntityConfig(
                    'Test\NewEntity',
                    ['state' => ExtendScope::STATE_NEW]
                ),
                false
            ],
            'created, but not committed field'                           => [
                $this->getFieldConfig(
                    'Test\Entity',
                    'new_field',
                    ['state' => ExtendScope::STATE_NEW]
                ),
                false
            ],
            'created in a migration entity'                              => [
                $this->getEntityConfig(
                    'Test\CreatedEntity',
                    ['state' => ExtendScope::STATE_NEW]
                ),
                true
            ],
            'created in a migration field'                               => [
                $this->getFieldConfig(
                    'Test\Entity',
                    'created_field',
                    ['state' => ExtendScope::STATE_NEW]
                ),
                true
            ],
            'updated, but not committed entity'                          => [
                $this->getEntityConfig(
                    'Test\RequireUpdateEntity',
                    ['state' => ExtendScope::STATE_ACTIVE]
                ),
                false
            ],
            'updated, but not committed field'                           => [
                $this->getFieldConfig(
                    'Test\Entity',
                    'require_update_field',
                    ['state' => ExtendScope::STATE_ACTIVE]
                ),
                false
            ],
            'marked as to be deleted entity'                             => [
                $this->getEntityConfig(
                    'Test\ToDeleteEntity',
                    ['state' => ExtendScope::STATE_DELETE]
                ),
                false
            ],
            'marked as to be deleted field'                              => [
                $this->getFieldConfig(
                    'Test\Entity',
                    'to_delete_field',
                    ['state' => ExtendScope::STATE_DELETE]
                ),
                false
            ],
            'marked as to be deleted, but changed in a migration entity' => [
                $this->getEntityConfig(
                    'Test\ToDeleteEntity',
                    ['state' => ExtendScope::STATE_UPDATE]
                ),
                true
            ],
            'marked as to be deleted, but changed in a migration field'  => [
                $this->getFieldConfig(
                    'Test\Entity',
                    'to_delete_field',
                    ['state' => ExtendScope::STATE_UPDATE]
                ),
                true
            ],
        ];
    }

    private function getEntityConfig(string $className, mixed $values): ConfigInterface
    {
        $config = new Config(new EntityConfigId('extend', $className));
        $config->setValues($values);

        return $config;
    }

    private function getFieldConfig(string $className, string $fieldName, mixed $values): ConfigInterface
    {
        $config = new Config(new FieldConfigId('extend', $className, $fieldName));
        $config->setValues($values);

        return $config;
    }
}
