<?php

namespace Oro\Bundle\TranslationBundle\Tests\Unit\Translation;

use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Bundle\TranslationBundle\Translation\IdentityTranslator;
use PHPUnit\Framework\TestCase;

class IdentityTranslatorTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private IdentityTranslator $identityTranslator;

    #[\Override]
    protected function setUp(): void
    {
        $this->identityTranslator = new IdentityTranslator();

        $this->setUpLoggerMock($this->identityTranslator);
    }

    public function testReturnMessageIfExactlyOneStandardRuleIsGiven(): void
    {
        self::assertEquals(
            'There are two apples',
            $this->identityTranslator->trans('There are two apples', ['%count%' => 2], null, 'en')
        );
    }

    /**
     * @dataProvider getNonMatchingMessages
     */
    public function testLogErrorIfMatchingMessageCannotBeFound(string $message, int $number, string $expected): void
    {
        $this->assertLoggerWarningMethodCalled();

        self::assertSame($expected, $this->identityTranslator->trans($message, ['%count%' => $number], null, 'ru'));
    }

    public function getNonMatchingMessages(): array
    {
        return [
            ['{0} Ноль яблок|{1} Одно яблоко', 2, 'Одно яблоко'],
            ['{1} Одно яблоко|]1,Inf] тут %count% яблок', 0, 'тут 0 яблок'],
            ['{1} Одно яблоко|]2,Inf] тут %count% яблок', 2, 'тут 2 яблок'],
            ['{0} Ноль яблок|Одно яблоко|Два яблока', 100, 'Два яблока'],
            ['Одно яблоко|Два яблока', 100, 'Два яблока'],
            ['|', 15, ''],
        ];
    }

    /**
     * @dataProvider transDataProvider
     *
     * @param string $expected
     * @param string $message
     * @param int|float $number
     */
    public function testTrans(string $expected, string $message, $number): void
    {
        $this->assertLoggerNotCalled();

        self::assertEquals($expected, $this->identityTranslator->trans($message, ['%count%' => $number], null, 'en'));
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function transDataProvider(): array
    {
        return [
            [
                'There are no apples',
                '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples',
                0,
            ],
            [
                'There are no apples',
                '{0}     There are no apples|{1} There is one apple|]1,Inf] There are %count% apples',
                0,
            ],
            [
                'There are no apples',
                '{0}There are no apples|{1} There is one apple|]1,Inf] There are %count% apples',
                0,
            ],

            [
                'There is one apple',
                '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples',
                1,
            ],

            [
                'There are 10 apples',
                '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples',
                10,
            ],
            [
                'There are 10 apples',
                '{0} There are no apples|{1} There is one apple|]1,Inf]There are %count% apples',
                10,
            ],
            [
                'There are 10 apples',
                '{0} There are no apples|{1} There is one apple|]1,Inf]     There are %count% apples',
                10,
            ],

            ['There are 0 apples', 'There is one apple|There are %count% apples', 0],
            ['There is one apple', 'There is one apple|There are %count% apples', 1],
            ['There are 10 apples', 'There is one apple|There are %count% apples', 10],

            ['There are 0 apples', 'one: There is one apple|more: There are %count% apples', 0],
            ['There is one apple', 'one: There is one apple|more: There are %count% apples', 1],
            ['There are 10 apples', 'one: There is one apple|more: There are %count% apples', 10],

            [
                'There are no apples',
                '{0} There are no apples|one: There is one apple|more: There are %count% apples',
                0,
            ],
            ['There is one apple', '{0} There are no apples|one: There is one apple|more: There are %count% apples', 1],
            [
                'There are 10 apples',
                '{0} There are no apples|one: There is one apple|more: There are %count% apples',
                10,
            ],

            ['', '{0}|{1} There is one apple|]1,Inf] There are %count% apples', 0],
            ['', '{0} There are no apples|{1}|]1,Inf] There are %count% apples', 1],

            // Indexed only tests which are Gettext PoFile* compatible strings.
            ['There are 0 apples', 'There is one apple|There are %count% apples', 0],
            ['There is one apple', 'There is one apple|There are %count% apples', 1],
            ['There are 2 apples', 'There is one apple|There are %count% apples', 2],

            // Tests for float numbers
            [
                'There is almost one apple',
                '{0} There are no apples|]0,1[ There is almost one apple|{1}' .
                ' There is one apple|[1,Inf] There is more than one apple',
                0.7,
            ],
            [
                'There is one apple',
                '{0} There are no apples|]0,1[There are %count% apples|{1}' .
                ' There is one apple|[1,Inf] There is more than one apple',
                1,
            ],
            [
                'There is more than one apple',
                '{0} There are no apples|]0,1[There are %count% apples|{1}' .
                ' There is one apple|[1,Inf] There is more than one apple',
                1.7,
            ],
            [
                'There are no apples',
                '{0} There are no apples|]0,1[There are %count% apples|{1}' .
                ' There is one apple|[1,Inf] There is more than one apple',
                0,
            ],
            [
                'There are no apples',
                '{0} There are no apples|]0,1[There are %count% apples|{1}' .
                ' There is one apple|[1,Inf] There is more than one apple',
                0.0,
            ],
            [
                'There are no apples',
                '{0.0} There are no apples|]0,1[There are %count% apples|{1}' .
                ' There is one apple|[1,Inf] There is more than one apple',
                0,
            ],

            // Test texts with new-lines
            // with double-quotes and \n in id & double-quotes and actual newlines in text
            [
                "This is a text with a\n            new-line in it. Selector = 0.",
                '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.',
                0,
            ],
            // with double-quotes and \n in id and single-quotes and actual newlines in text
            [
                "This is a text with a\n            new-line in it. Selector = 1.",
                '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.',
                1,
            ],
            [
                "This is a text with a\n            new-line in it. Selector > 1.",
                '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.',
                5,
            ],
            // with double-quotes and id split accros lines
            [
                'This is a text with a
            new-line in it. Selector = 1.',
                '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.',
                1,
            ],
            // with single-quotes and id split accros lines
            [
                'This is a text with a
            new-line in it. Selector > 1.',
                '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.',
                5,
            ],
            // with single-quotes and \n in text
            [
                'This is a text with a\nnew-line in it. Selector = 0.',
                '{0}This is a text with a\nnew-line in it. Selector = 0.|{1}This is a text with a\nnew-line in it.' .
                ' Selector = 1.|[1,Inf]This is a text with a\nnew-line in it. Selector > 1.',
                0,
            ],
            // with double-quotes and id split accros lines
            [
                "This is a text with a\nnew-line in it. Selector = 1.",
                "{0}This is a text with a\nnew-line in it. Selector = 0.|{1}This is a text with a\n" .
                "new-line in it. Selector = 1.|[1,Inf]This is a text with a\nnew-line in it. Selector > 1.",
                1,
            ],
            // esacape pipe
            [
                'This is a text with | in it. Selector = 0.',
                '{0}This is a text with || in it. Selector = 0.|{1}This is a text with || in it. Selector = 1.',
                0,
            ],
            // Empty plural set (2 plural forms) from a .PO file
            ['', '|', 1],
            // Empty plural set (3 plural forms) from a .PO file
            ['', '||', 1],
        ];
    }
}
