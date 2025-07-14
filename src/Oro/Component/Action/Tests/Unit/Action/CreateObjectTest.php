<?php

namespace Oro\Component\Action\Tests\Unit\Action;

use Oro\Component\Action\Action\CreateObject;
use Oro\Component\Action\Exception\InvalidParameterException;
use Oro\Component\ConfigExpression\ContextAccessor;
use Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ItemStub;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyAccess\PropertyPath;

class CreateObjectTest extends TestCase
{
    private CreateObject $action;

    #[\Override]
    protected function setUp(): void
    {
        $this->action = new CreateObject(new ContextAccessor());
        $this->action->setDispatcher($this->createMock(EventDispatcher::class));
    }

    public function testInitializeExceptionNoClassName(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Class name parameter is required');

        $this->action->initialize(['some' => 1, 'attribute' => $this->createMock(PropertyPath::class)]);
    }

    public function testInitializeExceptionNoAttribute(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Attribute name parameter is required');

        $this->action->initialize(['class' => 'stdClass']);
    }

    public function testInitializeExceptionInvalidAttribute(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Attribute must be valid property definition.');

        $this->action->initialize(['class' => 'stdClass', 'attribute' => 'string']);
    }

    public function testExceptionInvalidData(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Object data must be an array.');

        $this->action->initialize([
            'class' => 'stdClass',
            'attribute' => $this->createMock(PropertyPath::class),
            'data' => 'string_value'
        ]);

        $this->action->execute(new ItemStub());
    }

    public function testInitializeExceptionInvalidArguments(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Object constructor arguments must be an array.');

        $this->action->initialize([
            'class' => 'stdClass',
            'attribute' => $this->createMock(PropertyPath::class),
            'arguments' => 'string_value'
        ]);
    }

    public function testInitialize(): void
    {
        $options = ['class' => 'stdClass', 'attribute' => $this->createMock(PropertyPath::class)];
        self::assertEquals($this->action, $this->action->initialize($options));
        self::assertEquals($options, ReflectionUtil::getPropertyValue($this->action, 'options'));
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $options, array $contextData = [], ?array $expectedData = null): void
    {
        $context = new ItemStub($contextData);
        $attributeName = (string)$options['attribute'];
        $this->action->initialize($options);
        $this->action->execute($context);
        self::assertNotNull($context->{$attributeName});
        self::assertInstanceOf($options['class'], $context->{$attributeName});

        if ($context->{$attributeName} instanceof ItemStub) {
            /** @var ItemStub $entity */
            $entity = $context->{$attributeName};
            if (!$expectedData) {
                $expectedData = !empty($options['data']) ? $options['data'] : [];
            }
            self::assertInstanceOf($options['class'], $entity);
            self::assertEquals($expectedData, $entity->getData());
        }
    }

    public function executeDataProvider(): array
    {
        return [
            'without data' => [
                'options' => [
                    'class'     => ItemStub::class,
                    'attribute' => new PropertyPath('test_attribute'),
                ]
            ],
            'with data' => [
                'options' => [
                    'class'     => ItemStub::class,
                    'attribute' => new PropertyPath('test_attribute'),
                    'data'      => ['key1' => new PropertyPath('test_attribute'), 'key2' => 'value2'],
                ],
                ['test_attribute' => 'test_value'],
                ['key1' => 'test_value', 'key2' => 'value2']
            ],
            'with arguments' => [
                'options' => [
                    'class'     => \DateTime::class,
                    'attribute' => new PropertyPath('test_attribute'),
                    'arguments' => ['now'],
                ]
            ],
            'with complex arguments' => [
                'options' => [
                    'class'     => ItemStub::class,
                    'attribute' => new PropertyPath('test_attribute'),
                    'arguments' => [['test', new PropertyPath('test_attribute')]],
                ],
                ['test_attribute' => 'test_value'],
                ['test', 'test_value']
            ],
            'with property data' => [
                'options' => [
                    'class'     => ItemStub::class,
                    'attribute' => new PropertyPath('test_attribute'),
                    'arguments' => [],
                    'data' => new PropertyPath('data_attr'),
                ],
                ['data_attr' => ['key1' => 'val1']],
                ['key1' => 'val1']
            ]
        ];
    }
}
