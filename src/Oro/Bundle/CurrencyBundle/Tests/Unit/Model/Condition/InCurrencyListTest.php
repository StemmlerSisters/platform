<?php

namespace Oro\Bundle\CurrencyBundle\Tests\Unit\Model\Condition;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\CurrencyBundle\Entity\MultiCurrency;
use Oro\Bundle\CurrencyBundle\Model\Condition\InCurrencyList;
use Oro\Bundle\CurrencyBundle\Tests\Unit\Provider\CurrencyListProviderStub;
use Oro\Component\ConfigExpression\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class InCurrencyListTest extends TestCase
{
    private InCurrencyList $condition;

    #[\Override]
    protected function setUp(): void
    {
        $this->condition = new InCurrencyList(new CurrencyListProviderStub());
    }

    public function testEvaluateSuccess(): void
    {
        $this->condition->initialize([
            'entity' => MultiCurrency::create(100, 'USD')
        ]);
        $this->assertTrue($this->condition->evaluate(new \stdClass(), new ArrayCollection()));
    }

    public function testEvaluateIncorrectCurrency(): void
    {
        $this->condition->initialize([MultiCurrency::create(100, 'GBP')]);
        $this->assertFalse(
            $this->condition->evaluate(new \stdClass(), new ArrayCollection()),
            'Unknown currency is used, validation should fail but it is not'
        );
    }

    public function testEvoluteWithIncorrectData(): void
    {
        try {
            $this->condition->initialize([
                'entity' => new \stdClass()
            ]);
            $this->condition->evaluate(new \stdClass(), new ArrayCollection());

            $this->fail('Right now we only support multycurrency class');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('Entity must be object of', $e->getMessage());
        }
    }

    public function testInitializeWithIncorrectData(): void
    {
        try {
            $this->condition->initialize(['test' => 'foo']);
            $this->fail('Exception should be thrown if we have no entity option');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('Option "entity" must be set', $e->getMessage());
        }

        try {
            $this->condition->initialize([]);
            $this->fail('Exception should be thrown if we have no options at all');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('Options must have 1 element', $e->getMessage());
        }
    }

    public function testGetName(): void
    {
        $this->assertEquals(
            'in_currency_list',
            $this->condition->getName(),
            'Name field was changed but be careful and check all workflows before fix this test'
        );
    }
}
