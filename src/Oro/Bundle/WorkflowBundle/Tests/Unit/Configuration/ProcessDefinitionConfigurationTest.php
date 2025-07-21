<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Configuration;

use Oro\Bundle\WorkflowBundle\Configuration\ProcessDefinitionConfiguration;
use PHPUnit\Framework\TestCase;

class ProcessDefinitionConfigurationTest extends TestCase
{
    private ProcessDefinitionConfiguration $configuration;

    #[\Override]
    protected function setUp(): void
    {
        $this->configuration = new ProcessDefinitionConfiguration();
    }

    /**
     * @dataProvider processDataProvider
     */
    public function testProcess(array $input, array $expected): void
    {
        $this->assertEquals($expected, $this->configuration->processConfiguration($input));
    }

    public function processDataProvider(): array
    {
        return [
            'minimum data' => [
                'input' => [
                    'label' => 'My Label',
                    'entity' => 'My\Entity',
                ],
                'expected' => [
                    'label' => 'My Label',
                    'entity' => 'My\Entity',
                    'enabled' => true,
                    'order' => 0,
                    'exclude_definitions'   => [],
                    'actions_configuration' => [],
                    'preconditions' => []
                ],
            ],
            'maximum data' => [
                'input' => [
                    'name' => 'my_definition',
                    'label' => 'My Label',
                    'enabled' => false,
                    'entity' => 'My\Entity',
                    'order' => 10,
                    'exclude_definitions'   => [],
                    'actions_configuration' => ['key' => 'value'],
                    'preconditions' => ['test'],
                ],
                'expected' => [
                    'name' => 'my_definition',
                    'label' => 'My Label',
                    'enabled' => false,
                    'entity' => 'My\Entity',
                    'order' => 10,
                    'exclude_definitions'   => [],
                    'actions_configuration' => ['key' => 'value'],
                    'preconditions' => ['test'],
                ],
            ],
        ];
    }
}
