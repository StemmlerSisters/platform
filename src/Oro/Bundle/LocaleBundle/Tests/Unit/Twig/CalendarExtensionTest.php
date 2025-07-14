<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Twig;

use Oro\Bundle\LocaleBundle\Model\Calendar;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\LocaleBundle\Twig\CalendarExtension;
use Oro\Component\Testing\Unit\TwigExtensionTestCaseTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CalendarExtensionTest extends TestCase
{
    use TwigExtensionTestCaseTrait;

    private LocaleSettings&MockObject $localeSettings;
    private Calendar&MockObject $calendar;
    private CalendarExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->localeSettings = $this->createMock(LocaleSettings::class);
        $this->calendar = $this->createMock(Calendar::class);

        $container = self::getContainerBuilder()
            ->add(LocaleSettings::class, $this->localeSettings)
            ->getContainer($this);

        $this->extension = new CalendarExtension($container);
    }

    public function testGetMonthNames(): void
    {
        $width = Calendar::WIDTH_NARROW;
        $locale = 'en_US';
        $expectedResult = ['expected_result'];

        $this->calendar->expects($this->once())
            ->method('getMonthNames')
            ->with($width)
            ->willReturn($expectedResult);

        $this->localeSettings->expects($this->once())
            ->method('getCalendar')
            ->with($locale)
            ->willReturn($this->calendar);

        $this->assertEquals(
            $expectedResult,
            self::callTwigFunction($this->extension, 'oro_calendar_month_names', [$width, $locale])
        );
    }

    public function testGetDayOfWeekNames(): void
    {
        $width = Calendar::WIDTH_ABBREVIATED;
        $locale = 'en_US';
        $expectedResult = ['expected_result'];

        $this->calendar->expects($this->once())
            ->method('getDayOfWeekNames')
            ->with($width)
            ->willReturn($expectedResult);

        $this->localeSettings->expects($this->once())
            ->method('getCalendar')
            ->with($locale)
            ->willReturn($this->calendar);

        $this->assertEquals(
            $expectedResult,
            self::callTwigFunction($this->extension, 'oro_calendar_day_of_week_names', [$width, $locale])
        );
    }

    public function testGetFirstDayOfWeek(): void
    {
        $locale = 'en_US';
        $expectedResult = Calendar::DOW_MONDAY;

        $this->calendar->expects($this->once())
            ->method('getFirstDayOfWeek')
            ->with()
            ->willReturn($expectedResult);

        $this->localeSettings->expects($this->once())
            ->method('getCalendar')
            ->with($locale)
            ->willReturn($this->calendar);

        $this->assertEquals(
            $expectedResult,
            self::callTwigFunction($this->extension, 'oro_calendar_first_day_of_week', [$locale])
        );
    }
}
