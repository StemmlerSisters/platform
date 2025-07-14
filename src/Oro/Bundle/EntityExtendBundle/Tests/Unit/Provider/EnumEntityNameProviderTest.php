<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Provider;

use Oro\Bundle\EntityBundle\Provider\EntityNameProviderInterface;
use Oro\Bundle\EntityExtendBundle\Provider\EnumEntityNameProvider;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestClass;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestEnumValue;
use PHPUnit\Framework\TestCase;

class EnumEntityNameProviderTest extends TestCase
{
    private EnumEntityNameProvider $enumEntityNameProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->enumEntityNameProvider = new EnumEntityNameProvider();
    }

    /**
     * @dataProvider getNameProvider
     */
    public function testGetName(array $args, $expectedValue): void
    {
        $this->assertSame(
            $expectedValue,
            call_user_func_array([$this->enumEntityNameProvider, 'getName'], $args)
        );
    }

    public function getNameProvider(): array
    {
        return [
            'full version of enum' => [
                [
                    EntityNameProviderInterface::FULL,
                    null,
                    new TestEnumValue('test_enum_code', 'Test', 'idValue'),
                ],
                'Test',
            ],
            'short version of enum' => [
                [
                    EntityNameProviderInterface::SHORT,
                    null,
                    new TestEnumValue('test_enum_code', 'Test', 'idValue', 1),
                ],
                'Test',
            ],
            'ful version of unsupported class' => [
                [
                    EntityNameProviderInterface::FULL,
                    null,
                    new TestClass(),
                ],
                false,
            ],
            'short version of unsupported class' => [
                [
                    EntityNameProviderInterface::SHORT,
                    null,
                    new TestClass(),
                ],
                false,
            ],
        ];
    }

    /**
     * @dataProvider getNameDQLProvider
     */
    public function testGetNameDQL(array $args, $expectedValue): void
    {
        $this->assertSame(
            $expectedValue,
            call_user_func_array([$this->enumEntityNameProvider, 'getNameDQL'], $args)
        );
    }

    public function getNameDQLProvider(): array
    {
        return [
            'full version of enum' => [
                [
                    EntityNameProviderInterface::FULL,
                    null,
                    TestEnumValue::class,
                    't',
                ],
                't.name',
            ],
            'short version of enum' => [
                [
                    EntityNameProviderInterface::SHORT,
                    null,
                    TestEnumValue::class,
                    'e',
                ],
                'e.name',
            ],
            'ful version of unsupported class' => [
                [
                    EntityNameProviderInterface::FULL,
                    null,
                    TestClass::class,
                    't',
                ],
                false,
            ],
            'short version of unsupported class' => [
                [
                    EntityNameProviderInterface::SHORT,
                    null,
                    TestClass::class,
                    'e',
                ],
                false,
            ],
        ];
    }
}
