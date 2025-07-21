<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Twig;

use Oro\Bundle\LocaleBundle\Converter\DateTimeFormatConverterInterface;
use Oro\Bundle\LocaleBundle\Converter\DateTimeFormatConverterRegistry;
use Oro\Bundle\LocaleBundle\Twig\DateFormatExtension;
use Oro\Component\Testing\Unit\TwigExtensionTestCaseTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DateFormatExtensionTest extends TestCase
{
    use TwigExtensionTestCaseTrait;

    private const TEST_TYPE = 'test_format_type';
    private const TEST_FORMAT = 'MMM, d y t';

    private DateTimeFormatConverterRegistry&MockObject $converterRegistry;
    private DateFormatExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->converterRegistry = $this->createMock(DateTimeFormatConverterRegistry::class);

        $container = self::getContainerBuilder()
            ->add('oro_locale.format_converter.date_time.registry', $this->converterRegistry)
            ->getContainer($this);

        $this->extension = new DateFormatExtension($container);
    }

    public function testGetDateFormat(): void
    {
        $locale = 'en';
        $dateType = 'short';

        $formatConverter = $this->createMock(DateTimeFormatConverterInterface::class);
        $formatConverter->expects($this->once())
            ->method('getDateFormat')
            ->with($dateType, $locale)
            ->willReturn(self::TEST_FORMAT);

        $this->converterRegistry->expects($this->once())
            ->method('getFormatConverter')
            ->with(self::TEST_TYPE)
            ->willReturn($formatConverter);

        $this->assertEquals(
            self::TEST_FORMAT,
            self::callTwigFunction($this->extension, 'oro_date_format', [self::TEST_TYPE, $dateType, $locale])
        );
    }

    public function testGetTimeFormat(): void
    {
        $locale = 'en';
        $timeType = 'short';

        $formatConverter = $this->createMock(DateTimeFormatConverterInterface::class);
        $formatConverter->expects($this->once())
            ->method('getTimeFormat')
            ->with($timeType, $locale)
            ->willReturn(self::TEST_FORMAT);

        $this->converterRegistry->expects($this->once())
            ->method('getFormatConverter')
            ->with(self::TEST_TYPE)
            ->willReturn($formatConverter);

        $this->assertEquals(
            self::TEST_FORMAT,
            self::callTwigFunction($this->extension, 'oro_time_format', [self::TEST_TYPE, $timeType, $locale])
        );
    }

    public function testGetDateTimeFormat(): void
    {
        $locale = 'en';
        $dateType = 'medium';
        $timeType = 'short';

        $formatConverter = $this->createMock(DateTimeFormatConverterInterface::class);
        $formatConverter->expects($this->once())
            ->method('getDateTimeFormat')
            ->with($dateType, $timeType, $locale)
            ->willReturn(self::TEST_FORMAT);

        $this->converterRegistry->expects($this->once())
            ->method('getFormatConverter')
            ->with(self::TEST_TYPE)
            ->willReturn($formatConverter);

        $this->assertEquals(
            self::TEST_FORMAT,
            self::callTwigFunction(
                $this->extension,
                'oro_datetime_format',
                [self::TEST_TYPE, $dateType, $timeType, $locale]
            )
        );
    }

    public function testGetDateTimeFormatterList(): void
    {
        $formatConverters = [
            'first'  => $this->createMock(DateTimeFormatConverterInterface::class),
            'second' => $this->createMock(DateTimeFormatConverterInterface::class),
        ];
        $this->converterRegistry->expects($this->once())
            ->method('getFormatConverters')
            ->willReturn($formatConverters);

        $this->assertEquals(
            array_keys($formatConverters),
            self::callTwigFunction($this->extension, 'oro_datetime_formatter_list', [])
        );
    }
}
