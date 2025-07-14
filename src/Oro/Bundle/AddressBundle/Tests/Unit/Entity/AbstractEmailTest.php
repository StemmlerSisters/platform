<?php

namespace Oro\Bundle\AddressBundle\Tests\Unit\Entity;

use Oro\Bundle\AddressBundle\Entity\AbstractEmail;
use PHPUnit\Framework\TestCase;

class AbstractEmailTest extends TestCase
{
    private AbstractEmail $email;

    #[\Override]
    protected function setUp(): void
    {
        $this->email = $this->createEmail();
    }

    public function testConstructor(): void
    {
        $this->email = $this->createEmail('email@example.com');

        $this->assertEquals('email@example.com', $this->email->getEmail());
    }

    public function testId(): void
    {
        $this->assertNull($this->email->getId());
        $this->email->setId(100);
        $this->assertEquals(100, $this->email->getId());
    }

    public function testEmail(): void
    {
        $this->assertNull($this->email->getEmail());
        $this->email->setEmail('email@example.com');
        $this->assertEquals('email@example.com', $this->email->getEmail());
    }

    public function testToString(): void
    {
        $this->assertEquals('', (string)$this->email);
        $this->email->setEmail('email@example.com');
        $this->assertEquals('email@example.com', (string)$this->email);
    }

    public function testPrimary(): void
    {
        $this->assertFalse($this->email->isPrimary());
        $this->email->setPrimary(true);
        $this->assertTrue($this->email->isPrimary());
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->createEmail()->isEmpty());
        $this->assertFalse($this->createEmail('foo@example.com')->isEmpty());
    }

    /**
     * @dataProvider isEqualDataProvider
     */
    public function testIsEqual(AbstractEmail $first, ?AbstractEmail $second, bool $expectedResult): void
    {
        $this->assertEquals($expectedResult, $first->isEqual($second));
    }

    public function isEqualDataProvider(): array
    {
        $emailEmpty = $this->createEmail();
        $emailAddress = $this->createEmail('a@a.a');

        return [
            'both empty'           => [$emailEmpty, $emailEmpty, true],
            'one empty one unset'  => [$emailEmpty, null, false],
            'one empty'            => [$this->createEmail(100), $emailEmpty, false],
            'both with same id'    => [$this->createEmail('a@a.a', 100), $this->createEmail('b@b.b', 100), true],
            'equals not empty'     => [$emailAddress, $emailAddress, true],
            'not equals not empty' => [$emailAddress, $this->createEmail('b@b.b'), false],
        ];
    }

    private function createEmail(?string $email = null, ?int $id = null): AbstractEmail
    {
        $arguments = [];
        if ($email) {
            $arguments[] = $email;
        }

        $email = $this->getMockForAbstractClass(AbstractEmail::class, $arguments);
        if ($id) {
            $email->setId($id);
        }

        return $email;
    }
}
