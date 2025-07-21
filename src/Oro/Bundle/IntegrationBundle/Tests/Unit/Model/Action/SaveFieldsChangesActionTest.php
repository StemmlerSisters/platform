<?php

namespace Oro\Bundle\IntegrationBundle\Tests\Unit\Model\Action;

use Oro\Bundle\IntegrationBundle\Manager\FieldsChangesManager;
use Oro\Bundle\IntegrationBundle\Model\Action\SaveFieldsChangesAction;
use Oro\Bundle\WorkflowBundle\Model\ProcessData;
use Oro\Component\Action\Exception\InvalidParameterException;
use Oro\Component\ConfigExpression\ContextAccessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyAccess\PropertyPath;

class SaveFieldsChangesActionTest extends TestCase
{
    private SaveFieldsChangesAction $action;

    #[\Override]
    protected function setUp(): void
    {
        $contextAccessor = new ContextAccessor();
        $this->action = new SaveFieldsChangesAction($contextAccessor);
        $dispatcher = $this->createMock(EventDispatcher::class);
        $this->action->setDispatcher($dispatcher);
    }

    /**
     * @dataProvider initializeDataProvider
     */
    public function testInitializeFailed(array $options, ?string $message): void
    {
        if ($message) {
            $this->expectException(InvalidParameterException::class);
            $this->expectExceptionMessage($message);
        }

        $this->action->initialize($options);
    }

    public function initializeDataProvider(): array
    {
        return [
            'empty'     => [
                [],
                'changeSet parameter is required'
            ],
            'changeSet' => [
                ['changeSet' => ['value']],
                'Entity parameter is required'
            ],
            'full'      => [
                ['changeSet' => ['value'], 'entity' => ['value']],
                null
            ],
        ];
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecuteAction(array $options, array $context): void
    {
        $fieldsChangesManager = $this->createMock(FieldsChangesManager::class);

        if (!empty($context['changeSet'])) {
            $fieldsChangesManager->expects($this->once())
                ->method('setChanges')
                ->with(
                    $this->equalTo(empty($context['data']) ? null : $context['data']),
                    $this->equalTo(array_keys($context['changeSet']))
                );
        }

        $this->action->setFieldsChangesManager($fieldsChangesManager);
        $this->action->initialize($options);
        $this->action->execute(new ProcessData($context));
    }

    public function executeDataProvider(): array
    {
        return [
            [
                [
                    'entity'    => new PropertyPath('data'),
                    'changeSet' => new PropertyPath('changeSet'),
                ],
                [
                    'data'      => new \stdClass(),
                    'changeSet' => ['field' => ['old' => 1, 'new' => 2]],
                ]
            ],
            [
                [
                    'entity'    => new PropertyPath('entity'),
                    'changeSet' => new PropertyPath('changeSet'),
                ],
                ['changeSet' => []]
            ]
        ];
    }
}
