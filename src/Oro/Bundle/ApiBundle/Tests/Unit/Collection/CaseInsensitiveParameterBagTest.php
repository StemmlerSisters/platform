<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Collection;

use Oro\Bundle\ApiBundle\Collection\CaseInsensitiveParameterBag;
use PHPUnit\Framework\TestCase;

class CaseInsensitiveParameterBagTest extends TestCase
{
    private CaseInsensitiveParameterBag $caseInsensitiveParameterBag;

    #[\Override]
    protected function setUp(): void
    {
        $this->caseInsensitiveParameterBag = new CaseInsensitiveParameterBag();
    }

    /**
     * @dataProvider actionsDataProvider
     */
    public function testActions(string $key, string $value): void
    {
        $this->caseInsensitiveParameterBag->set($key, $value);
        $this->caseInsensitiveParameterBag->set(strtoupper($key), $value);
        $this->caseInsensitiveParameterBag->set(ucfirst($key), $value);
        $this->caseInsensitiveParameterBag->set(ucwords($key), $value);

        self::assertTrue($this->caseInsensitiveParameterBag->has($key));
        self::assertTrue($this->caseInsensitiveParameterBag->has(strtoupper($key)));
        self::assertTrue($this->caseInsensitiveParameterBag->has(ucfirst($key)));
        self::assertTrue($this->caseInsensitiveParameterBag->has(ucwords($key)));

        self::assertSame($value, $this->caseInsensitiveParameterBag->get($key));
        self::assertSame($value, $this->caseInsensitiveParameterBag->get(strtoupper($key)));
        self::assertSame($value, $this->caseInsensitiveParameterBag->get(ucfirst($key)));
        self::assertSame($value, $this->caseInsensitiveParameterBag->get(ucwords($key)));

        self::assertCount(1, $this->caseInsensitiveParameterBag->toArray());

        $this->caseInsensitiveParameterBag->remove($key);

        self::assertCount(0, $this->caseInsensitiveParameterBag->toArray());
    }

    public function actionsDataProvider(): array
    {
        return [
            ['key' => 'key1', 'value' => 'value1'],
            ['key' => 'KeY2', 'value' => 'value2'],
            ['key' => 'three words key', 'value' => 'value3'],
            ['key' => 'SoMe KeY', 'value' => 'value4']
        ];
    }
}
