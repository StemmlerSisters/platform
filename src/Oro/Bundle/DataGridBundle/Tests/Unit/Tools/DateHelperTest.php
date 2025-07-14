<?php

namespace Oro\Bundle\DataGridBundle\Tests\Unit\Tools;

use Oro\Bundle\DataGridBundle\Tools\DateHelper;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DateHelperTest extends TestCase
{
    private LocaleSettings&MockObject $localeSettings;
    private DateHelper $dateHelper;

    #[\Override]
    protected function setUp(): void
    {
        $this->localeSettings = $this->createMock(LocaleSettings::class);

        $this->dateHelper = new DateHelper($this->localeSettings);
    }

    public function testGetTimeZoneOffset(): void
    {
        $this->localeSettings->expects(self::once())
            ->method('getTimeZone')
            ->willReturn('Asia/Tokyo');

        self::assertEquals('+09:00', $this->dateHelper->getTimeZoneOffset());
        // test that the offset is cached
        self::assertEquals('+09:00', $this->dateHelper->getTimeZoneOffset());
    }

    public function testGetTimeZoneOffsetForUTC(): void
    {
        $this->localeSettings->expects(self::once())
            ->method('getTimeZone')
            ->willReturn('UTC');

        self::assertEquals('+00:00', $this->dateHelper->getTimeZoneOffset());
    }

    public function testGetConvertTimezoneExpression(): void
    {
        $this->localeSettings->expects(self::once())
            ->method('getTimeZone')
            ->willReturn('Asia/Tokyo');

        self::assertEquals(
            'CONVERT_TZ(e.createdAt, \'+00:00\', \'+09:00\')',
            $this->dateHelper->getConvertTimezoneExpression('e.createdAt')
        );
    }

    public function testGetConvertTimezoneExpressionForUTC(): void
    {
        $this->localeSettings->expects(self::once())
            ->method('getTimeZone')
            ->willReturn('UTC');

        self::assertEquals(
            'e.createdAt',
            $this->dateHelper->getConvertTimezoneExpression('e.createdAt')
        );
    }
}
