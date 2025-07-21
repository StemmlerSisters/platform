<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\Provider;

use Oro\Bundle\UserBundle\Model\Gender;
use Oro\Bundle\UserBundle\Provider\GenderProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class GenderProviderTest extends TestCase
{
    private GenderProvider $genderProvider;

    #[\Override]
    protected function setUp(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->exactly(2))
            ->method('trans')
            ->willReturnCallback(function ($id) {
                return $id . '.translated';
            });

        $this->genderProvider = new GenderProvider($translator);
    }

    public function testGetChoices(): void
    {
        $expectedChoices = [
            'oro.user.gender.male.translated' => Gender::MALE,
            'oro.user.gender.female.translated' => Gender::FEMALE
        ];
        // run two times to test internal cache
        $this->assertEquals($expectedChoices, $this->genderProvider->getChoices());
        $this->assertEquals($expectedChoices, $this->genderProvider->getChoices());
    }

    public function testGetLabelByName(): void
    {
        $this->assertEquals(
            'oro.user.gender.male.translated',
            $this->genderProvider->getLabelByName(Gender::MALE)
        );
        $this->assertEquals(
            'oro.user.gender.female.translated',
            $this->genderProvider->getLabelByName(Gender::FEMALE)
        );
    }

    public function testGetLabelByNameUnknownGender(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown gender with name "alien"');

        $this->genderProvider->getLabelByName('alien');
    }
}
