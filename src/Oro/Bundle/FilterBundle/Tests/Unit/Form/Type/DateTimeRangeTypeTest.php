<?php

namespace Oro\Bundle\FilterBundle\Tests\Unit\Form\Type;

use Oro\Bundle\FilterBundle\Form\Type\DateRangeType;
use Oro\Bundle\FilterBundle\Form\Type\DateTimeRangeType;
use Oro\Bundle\FilterBundle\Tests\Unit\Fixtures\CustomFormExtension;
use Oro\Bundle\FormBundle\Form\Extension\DateTimeExtension;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Component\Testing\Unit\PreloadedExtension;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class DateTimeRangeTypeTest extends AbstractTypeTestCase
{
    private DateTimeRangeType $type;

    #[\Override]
    protected function setUp(): void
    {
        $this->defaultTimezone = 'Pacific/Honolulu';

        $localeSettings = $this->getMockBuilder(LocaleSettings::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTimezone'])
            ->getMock();
        $localeSettings->expects(self::any())
            ->method('getTimezone')
            ->willReturn($this->defaultTimezone);

        $this->type = new DateTimeRangeType();
        $this->formExtensions[] = new CustomFormExtension([new DateRangeType($localeSettings)]);
        $this->formExtensions[] = new PreloadedExtension(
            [$this->type],
            [
                DateTimeType::class => [new DateTimeExtension()]
            ]
        );

        parent::setUp();
    }

    #[\Override]
    protected function getTestFormType(): AbstractType
    {
        return $this->type;
    }

    #[\Override]
    public function configureOptionsDataProvider(): array
    {
        return [
            [
                'defaultOptions' => [
                    'field_type' => DateTimeType::class,
                    'field_options' => [
                        'format' => 'yyyy-MM-dd HH:mm',
                        'html5' => false
                    ]
                ]
            ]
        ];
    }

    #[\Override]
    public function bindDataProvider(): array
    {
        return [
            'empty' => [
                'bindData' => ['start' => '', 'end' => ''],
                'formData' => ['start' => null, 'end' => null],
                'viewData' => [
                    'value' => ['start' => '', 'end' => ''],
                ],
            ],
            'default timezone' => [
                'bindData' => ['start' => '2012-01-01 13:00', 'end' => '2013-01-01 18:00'],
                'formData' => [
                    'start' => $this->createDateTime('2012-01-01 23:00', 'UTC'),
                    'end' => $this->createDateTime('2013-01-02 04:00', 'UTC')
                ],
                'viewData' => [
                    'value' => ['start' => '2012-01-01 13:00', 'end' => '2013-01-01 18:00'],
                ],
            ],
            'custom timezone' => [
                'bindData' => ['start' => '2010-06-02T03:04:00-10:00', 'end' => '2013-06-02T03:04:00-10:00'],
                'formData' => [
                    'start' => $this->createDateTime('2010-06-02 03:04', 'America/New_York')
                        ->setTimezone(new \DateTimeZone('America/Los_Angeles')),
                    'end' => $this->createDateTime('2013-06-02 03:04:00', 'America/New_York')
                        ->setTimezone(new \DateTimeZone('America/Los_Angeles')),
                ],
                'viewData' => [
                    'value' => ['start' => '2010-06-02T03:04:00', 'end' => '2013-06-02T03:04:00'],
                ],
                'customOptions' => [
                    'field_options' => [
                        'model_timezone' => 'America/Los_Angeles',
                        'view_timezone' => 'America/New_York',
                        'format' => "yyyy-MM-dd'T'HH:mm:ss"
                    ]
                ]
            ],
        ];
    }

    /**
     * Creates date time object from date string
     */
    private function createDateTime(
        string $dateString,
        ?string $timeZone = null,
        ?string $format = 'yyyy-MM-dd HH:mm'
    ): \DateTime {
        $pattern = $format ?: null;

        if (!$timeZone) {
            $timeZone = date_default_timezone_get();
        }

        $calendar = \IntlDateFormatter::GREGORIAN;
        $intlDateFormatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            $timeZone,
            $calendar,
            $pattern
        );
        $intlDateFormatter->setLenient(false);
        $timestamp = $intlDateFormatter->parse($dateString);

        if (intl_get_error_code() != 0) {
            throw new \Exception(intl_get_error_message());
        }

        // read timestamp into DateTime object - the formatter delivers in UTC
        $dateTime = new \DateTime(sprintf('@%s UTC', $timestamp));
        if ('UTC' !== $timeZone) {
            try {
                $dateTime->setTimezone(new \DateTimeZone($timeZone));
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $dateTime;
    }
}
