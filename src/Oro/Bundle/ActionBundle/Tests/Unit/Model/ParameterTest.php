<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Model;

use Oro\Bundle\ActionBundle\Model\Parameter;
use Oro\Component\Testing\Unit\EntityTestCaseTrait;
use PHPUnit\Framework\TestCase;

class ParameterTest extends TestCase
{
    use EntityTestCaseTrait;

    private Parameter $parameter;

    #[\Override]
    protected function setUp(): void
    {
        $this->parameter = new Parameter('test');
    }

    public function testSimpleGettersAndSetters(): void
    {
        $this->assertEquals('test', $this->parameter->getName());
        self::assertPropertyAccessors(
            $this->parameter,
            [
                ['type', 'TestType'],
                ['message', 'Test Message'],
            ]
        );
    }

    public function testDefaultBehavior(): void
    {
        $this->assertFalse($this->parameter->hasMessage());
        $this->parameter->setMessage(null);
        $this->assertFalse($this->parameter->hasMessage());
        $this->parameter->setMessage('');
        $this->assertFalse($this->parameter->hasMessage());
        $this->parameter->setMessage(false);
        $this->assertFalse($this->parameter->hasMessage());

        $this->assertTrue($this->parameter->isRequired());
        $this->assertFalse($this->parameter->hasDefault());
        $this->assertFalse($this->parameter->hasTypeHint());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'Parameter `test` has no default value set. ' .
            'Please check `hasDefault() === true` or `isRequired() === false` before default value retrieval'
        );

        $this->parameter->getDefault();
    }

    /**
     * @dataProvider defaultValueProvider
     */
    public function testGetDefaultValue(mixed $value): void
    {
        $this->parameter->setDefault($value);

        $this->assertTrue($this->parameter->hasDefault());

        $this->assertSame($value, $this->parameter->getDefault());
    }

    public function defaultValueProvider(): array
    {
        return [
            [''],
            ['test'],
            [0],
            [1],
            [null],
            [true],
            [false],
            [[]],
            [(object)[]]
        ];
    }

    public function testToString(): void
    {
        $this->assertEquals('test', (string)$this->parameter);
    }

    public function testNoDefaultConstant(): void
    {
        $this->parameter->setDefault(Parameter::NO_DEFAULT);

        $this->assertFalse($this->parameter->hasDefault());
    }
}
