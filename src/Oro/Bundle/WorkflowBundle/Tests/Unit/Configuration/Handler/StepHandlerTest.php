<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Configuration\Handler;

use Oro\Bundle\WorkflowBundle\Configuration\Handler\StepHandler;
use Oro\Bundle\WorkflowBundle\Configuration\WorkflowConfiguration;
use PHPUnit\Framework\TestCase;

class StepHandlerTest extends TestCase
{
    private StepHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->handler = new StepHandler();
    }

    /**
     * @dataProvider handleDataProvider
     */
    public function testHandle(array $expected, array $input): void
    {
        $this->assertEquals($expected, $this->handler->handle($input));
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function handleDataProvider(): array
    {
        return [
            'simple configuration' => [
                'expected' => [
                    'start_step' => 'first_step',
                    WorkflowConfiguration::NODE_STEPS => [
                        [
                            'name' => 'step:starting_point',
                            'order' => -1,
                            '_is_start' => true,
                            'is_final' => '',
                            'allowed_transitions' => ['start_transition'],
                        ],
                        [
                            'name' => 'first_step',
                            'order' => 10,
                            'label' => 'First Step',
                            '_is_start' => false,
                            'is_final' => true,
                            'allowed_transitions' => [],
                        ],
                    ],
                    WorkflowConfiguration::NODE_TRANSITIONS => [
                        [
                            'name' => 'start_transition',
                            'is_start' => true,
                        ],
                    ],
                ],
                'input' => [
                    'start_step' => 'first_step',
                    WorkflowConfiguration::NODE_STEPS => [
                        [
                            'name' => 'step:starting_point',
                            'order' => -1,
                            '_is_start' => true,
                            'is_final' => false,
                            'allowed_transitions' => ['start_transition'],
                        ],
                        [
                            'name' => 'first_step',
                            'label' => 'First Step',
                            'order' => 10,
                            '_is_start' => false,
                            'is_final' => true,
                        ],
                    ],
                    WorkflowConfiguration::NODE_TRANSITIONS => [
                        [
                            'name' => 'start_transition'
                        ],
                    ],
                ],
            ],
            'full configuration' => [
                'expected' => [
                    'start_step' => null,
                    WorkflowConfiguration::NODE_STEPS => [
                        [
                            'name' => 'step:starting_point',
                            'order' => -1,
                            '_is_start' => true,
                            'is_final' => '',
                            'allowed_transitions' => ['start_transition']
                        ],
                        [
                            'name' => 'first_step',
                            'order' => 10,
                            'label' => 'First Step',
                            '_is_start' => false,
                            'is_final' => false,
                            'entity_acl' => ['attribute' => ['delete' => false]],
                            'allowed_transitions' => ['regular_transition'],
                            'position' => [1, 100],
                        ],
                        [
                            'name' => 'second_step',
                            'order' => 20,
                            '_is_start' => false,
                            'is_final' => true,
                            'allowed_transitions' => [],
                        ],
                    ],
                    WorkflowConfiguration::NODE_TRANSITIONS => [
                        [
                            'name' => 'start_transition',
                            'is_start' => true,
                        ],
                        [
                            'name' => 'regular_transition',
                            'is_start' => false,
                        ],
                    ],
                ],
                'input' => [
                    'start_step' => 'unknown_step',
                    WorkflowConfiguration::NODE_STEPS => [
                        [
                            'name' => 'step:starting_point',
                            'order' => -1,
                            '_is_start' => true,
                            'is_final' => false,
                            'allowed_transitions' => ['start_transition'],
                        ],
                        [
                            'name' => 'first_step',
                            'label' => 'First Step',
                            'order' => 10,
                            '_is_start' => false,
                            'is_final' => false,
                            'entity_acl' => ['attribute' => ['delete' => false]],
                            'allowed_transitions' => ['regular_transition', 'unknown_transition'],
                            'position' => [1, 100]
                        ],
                        [
                            'name' => 'second_step',
                            'order' => 20,
                            '_is_start' => false,
                            'is_final' => true,
                        ],
                    ],
                    WorkflowConfiguration::NODE_TRANSITIONS => [
                        [
                            'name' => 'start_transition',
                            'is_start' => true,
                        ],
                        [
                            'name' => 'regular_transition',
                            'is_start' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testHandleEmptyConfiguration(): void
    {
        $configuration = [
            WorkflowConfiguration::NODE_STEPS => [
                ['is_final' => true]
            ],
        ];

        $result = $this->handler->handle($configuration);

        $steps = $result[WorkflowConfiguration::NODE_STEPS];
        $this->assertCount(1, $steps);
        $step = current($steps);

        $this->assertArrayHasKey('name', $step);

        $this->assertStringStartsWith('step_', $step['name']);
    }
}
