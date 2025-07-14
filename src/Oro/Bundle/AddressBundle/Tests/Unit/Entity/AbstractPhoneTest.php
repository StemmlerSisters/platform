<?php

namespace Oro\Bundle\AddressBundle\Tests\Unit\Entity;

use Oro\Bundle\AddressBundle\Entity\AbstractPhone;
use PHPUnit\Framework\TestCase;

class AbstractPhoneTest extends TestCase
{
    private AbstractPhone $phone;

    #[\Override]
    protected function setUp(): void
    {
        $this->phone = $this->createPhone();
    }

    public function testConstructor(): void
    {
        $this->phone = $this->createPhone('080011223355');

        $this->assertEquals('080011223355', $this->phone->getPhone());
    }

    public function testId(): void
    {
        $this->assertNull($this->phone->getId());
        $this->phone->setId(100);
        $this->assertEquals(100, $this->phone->getId());
    }

    public function testPhone(): void
    {
        $this->assertNull($this->phone->getPhone());
        $this->phone->setPhone('080011223355');
        $this->assertEquals('080011223355', $this->phone->getPhone());
    }

    public function testToString(): void
    {
        $this->assertEquals('', (string)$this->phone);
        $this->phone->setPhone('080011223355');
        $this->assertEquals('080011223355', (string)$this->phone);
    }

    public function testPrimary(): void
    {
        $this->assertFalse($this->phone->isPrimary());
        $this->phone->setPrimary(true);
        $this->assertTrue($this->phone->isPrimary());
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->createPhone()->isEmpty());
        $this->assertFalse($this->createPhone('00110011')->isEmpty());
    }

    /**
     * @dataProvider isEqualDataProvider
     */
    public function testIsEqual(AbstractPhone $first, ?AbstractPhone $second, bool $expectedResult): void
    {
        $this->assertEquals($expectedResult, $first->isEqual($second));
    }

    public function isEqualDataProvider(): array
    {
        $phoneEmpty = $this->createPhone();
        $phoneSimple = $this->createPhone('123');

        return [
            'both empty'           => [$phoneEmpty, $phoneEmpty, true],
            'one empty'            => [$this->createPhone(100), $phoneEmpty, false],
            'one empty one unset'  => [$phoneEmpty, null, false],
            'both with same id'    => [$this->createPhone('123', 100), $this->createPhone('789', 100), true],
            'equals not empty'     => [$phoneSimple, $phoneSimple, true],
            'not equals not empty' => [$phoneSimple, $this->createPhone('789'), false],
        ];
    }

    private function createPhone(?string $phone = null, ?int $id = null): AbstractPhone
    {
        $arguments = [];
        if ($phone) {
            $arguments[] = $phone;
        }

        $phone = $this->getMockForAbstractClass(AbstractPhone::class, $arguments);
        if ($id) {
            $phone->setId($id);
        }

        return $phone;
    }
}
