<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Acl\Extension;

use Oro\Bundle\WorkflowBundle\Acl\Extension\TransitionLabel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class TransitionLabelTest extends TestCase
{
    private TranslatorInterface&MockObject $translator;

    #[\Override]
    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->expects(self::any())
            ->method('trans')
            ->willReturnCallback(function ($label, $parameters, $domain) {
                $result = 'translated: ' . $label;
                if (!empty($parameters)) {
                    foreach ($parameters as $key => $val) {
                        $result .= ' ' . $key . ': (' . $val . ')';
                    }
                }
                if (!empty($domain)) {
                    $result .= ' [domain: ' . $domain . ']';
                }

                return $result;
            });
    }

    public function testTrans(): void
    {
        $label = new TransitionLabel('transition', 'to_step', 'from_step');

        self::assertEquals(
            'translated: transition [domain: workflows]'
            . " (translated: from_step [domain: workflows] \u{2192} translated: to_step [domain: workflows])",
            $label->trans($this->translator)
        );
    }

    public function testTransWithoutFromStep(): void
    {
        $label = new TransitionLabel('transition', 'to_step');

        self::assertEquals(
            'translated: transition [domain: workflows]'
            . " (translated: (Start) [domain: jsmessages] \u{2192} translated: to_step [domain: workflows])",
            $label->trans($this->translator)
        );
    }

    public function testTransWithoutSteps(): void
    {
        $label = new TransitionLabel('transition');

        self::assertEquals(
            'translated: transition [domain: workflows]'
            . " (translated: (Start) [domain: jsmessages] \u{2192} )",
            $label->trans($this->translator)
        );
    }

    public function testSerialization(): void
    {
        $label = new TransitionLabel('transition', 'to_step', 'from_step');

        $unserialized = unserialize(serialize($label));
        $this->assertEquals($label, $unserialized);
        $this->assertNotSame($label, $unserialized);
    }

    public function testSetState(): void
    {
        $label = new TransitionLabel('transition', 'to_step', 'from_step');

        $unserialized = eval(sprintf('return %s;', var_export($label, true)));
        $this->assertEquals($label, $unserialized);
        $this->assertNotSame($label, $unserialized);
    }
}
