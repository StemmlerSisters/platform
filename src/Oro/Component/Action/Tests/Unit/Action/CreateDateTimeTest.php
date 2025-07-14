<?php

namespace Oro\Component\Action\Tests\Unit\Action;

use Oro\Component\Action\Action\CreateDateTime;
use Oro\Component\Action\Exception\InvalidParameterException;
use Oro\Component\ConfigExpression\ContextAccessor;
use Oro\Component\ConfigExpression\Tests\Unit\Fixtures\ItemStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyAccess\PropertyPath;

class CreateDateTimeTest extends TestCase
{
    private CreateDateTime $action;

    #[\Override]
    protected function setUp(): void
    {
        $this->action = new CreateDateTime(new ContextAccessor());
        $this->action->setDispatcher($this->createMock(EventDispatcher::class));
    }

    public function testInitializeExceptionNoAttribute(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Option "attribute" name parameter is required');

        $this->action->initialize([]);
    }

    public function testInitializeExceptionInvalidAttribute(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Option "attribute" must be valid property definition.');

        $this->action->initialize(['attribute' => 'string']);
    }

    public function testInitializeExceptionInvalidTime(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Option "time" must be a string, boolean given.');

        $this->action->initialize(['attribute' => new PropertyPath('test_attribute'), 'time' => true]);
    }

    public function testInitializeExceptionInvalidTimezone(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage(
            'Option "timezone" must be a PropertyPath or string or instance of DateTimeZone, boolean given.'
        );

        $this->action->initialize(['attribute' => new PropertyPath('test_attribute'), 'timezone' => true]);
    }

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecute(array $options, ?\DateTime $expectedResult = null, array $context = []): void
    {
        $context = new ItemStub($context);
        $attributeName = (string)$options['attribute'];
        $this->action->initialize($options);
        $this->action->execute($context);
        $this->assertNotNull($context->{$attributeName});
        $this->assertInstanceOf('DateTime', $context->{$attributeName});

        if ($expectedResult) {
            $this->assertEquals($expectedResult, $context->{$attributeName});
        }
    }

    public function executeDataProvider(): array
    {
        return [
            'without_time_and_timezone' => [
                'options' => [
                    'attribute' => new PropertyPath('test_attribute'),
                ],
            ],
            'with_time' => [
                'options' => [
                    'attribute' => new PropertyPath('test_attribute'),
                    'time'      => '2014-01-01 00:00:00',
                ],
                'expectedResult' => new \DateTime('2014-01-01 00:00:00', new \DateTimeZone('UTC')),
                'context' => [],
            ],
            'with_time_and_timezone_string' => [
                'options' => [
                    'attribute' => new PropertyPath('test_attribute'),
                    'time'      => '2014-01-01 00:00:00',
                    'timezone'  => 'Europe/London',
                ],
                'expectedResult' => new \DateTime('2014-01-01 00:00:00', new \DateTimeZone('Europe/London')),
                'context' => [],
            ],
            'with_time_and_timezone_object' => [
                'options' => [
                    'attribute' => new PropertyPath('test_attribute'),
                    'time'      => '2014-01-01 00:00:00',
                    'timezone'  => new \DateTimeZone('Europe/London'),
                ],
                'expectedResult' => new \DateTime('2014-01-01 00:00:00', new \DateTimeZone('Europe/London')),
                'context' => [],
            ],
            'with_time_and_timezone_path' => [
                'options' => [
                    'attribute' => new PropertyPath('test_attribute'),
                    'time'      => '2014-01-01 00:00:00',
                    'timezone'  => new PropertyPath('timeZoneNY'),
                ],
                'expectedResult' => new \DateTime('2014-01-01 00:00:00', new \DateTimeZone('America/New_York')),
                'context' => ['timeZoneNY' => 'America/New_York'],
            ]
        ];
    }
}
