<?php

namespace Oro\Component\Action\Tests\Unit\Action;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Component\Action\Action\Count;
use Oro\Component\Action\Exception\InvalidParameterException;
use Oro\Component\Action\Tests\Unit\Action\Stub\StubStorage;
use Oro\Component\ConfigExpression\ContextAccessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyAccess\PropertyPath;

class CountTest extends TestCase
{
    private Count $action;

    #[\Override]
    protected function setUp(): void
    {
        $this->action = new Count(new ContextAccessor());
        $this->action->setDispatcher($this->createMock(EventDispatcher::class));
    }

    public function testInitializeArrayException(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Parameter `value` is required.');

        $this->assertEquals($this->action, $this->action->initialize([]));
    }

    public function testInitializeAttributeException(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Parameter `attribute` is required.');

        $this->assertEquals($this->action, $this->action->initialize(['value' => []]));
    }

    public function testInitializeAttributeWrongException(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Parameter `attribute` must be a valid property definition.');

        $this->assertEquals($this->action, $this->action->initialize(['value' => [], 'attribute' => 'test']));
    }

    /**
     * @dataProvider objectDataProvider
     */
    public function testExecute(mixed $value, int $count): void
    {
        $context = new StubStorage();

        $this->action->initialize(['value' => $value, 'attribute' => new PropertyPath('test')]);
        $this->action->execute($context);

        $this->assertEquals(['test' => $count], $context->getValues());
    }

    public function objectDataProvider(): array
    {
        return [
            [[1, 2, 3], 3],
            [new ArrayCollection([1, 2, 3, 4, 5]), 5],
            [new \stdClass(), 0]
        ];
    }
}
