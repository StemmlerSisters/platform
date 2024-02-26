<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\Environment\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\TestFrameworkBundle\Entity\TestFrameworkEntityInterface;

/**
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_api_all_data_types')]
class TestAllDataTypes implements TestFrameworkEntityInterface
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    public ?int $id = null;

    #[ORM\Column(name: 'field_string', type: Types::STRING, nullable: true)]
    public ?string $fieldString = null;

    #[ORM\Column(name: 'field_text', type: Types::TEXT, nullable: true)]
    public ?string $fieldText = null;

    #[ORM\Column(name: 'field_int', type: Types::INTEGER, nullable: true)]
    public ?int $fieldInt = null;

    #[ORM\Column(name: 'field_smallint', type: Types::SMALLINT, nullable: true)]
    public ?int $fieldSmallInt = null;

    #[ORM\Column(name: 'field_bigint', type: Types::BIGINT, nullable: true)]
    public ?string $fieldBigInt = null;

    #[ORM\Column(name: 'field_boolean', type: Types::BOOLEAN, nullable: true)]
    public ?bool $fieldBoolean = null;

    #[ORM\Column(name: 'field_decimal', type: Types::DECIMAL, precision: 20, scale: 8, nullable: true)]
    public ?string $fieldDecimal = null;

    #[ORM\Column(name: 'field_decimal_default', type: Types::DECIMAL, nullable: true)]
    public ?string $fieldDecimalDefault = null;

    /**
     * @return float|null
     */
    #[ORM\Column(name: 'field_float', type: Types::FLOAT, nullable: true)]
    public $fieldFloat;

    /**
     * @var array
     */
    #[ORM\Column(name: 'field_array', type: Types::ARRAY, nullable: true)]
    public $fieldArray;

    /**
     * @var array
     */
    #[ORM\Column(name: 'field_simple_array', type: Types::SIMPLE_ARRAY, nullable: true)]
    public $fieldSimpleArray;

    /**
     * @var array
     */
    #[ORM\Column(name: 'field_json_array', type: 'json_array', nullable: true)]
    public $fieldJsonArray;

    /**
     * @var array
     */
    #[ORM\Column(name: 'field_json', type: Types::JSON, nullable: true)]
    public $fieldJson;

    #[ORM\Column(name: 'field_datetime', type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $fieldDateTime = null;

    #[ORM\Column(name: 'field_date', type: Types::DATE_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $fieldDate = null;

    #[ORM\Column(name: 'field_time', type: Types::TIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $fieldTime = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'field_guid', type: Types::GUID, nullable: true)]
    public $fieldGuid;

    /**
     * @var float
     */
    #[ORM\Column(name: 'field_percent', type: 'percent', nullable: true)]
    public $fieldPercent;

    /**
     * @var float
     */
    #[ORM\Column(name: 'field_percent_100', type: 'percent', nullable: true)]
    public $fieldPercent100;

    /**
     * @var float
     */
    #[ORM\Column(name: 'field_percent_round', type: 'percent', nullable: true)]
    public $fieldPercentRound;

    /**
     * @var float
     */
    #[ORM\Column(name: 'field_percent_100_round', type: 'percent', nullable: true)]
    public $fieldPercent100Round;

    /**
     * @var string
     */
    #[ORM\Column(name: 'field_money', type: 'money', nullable: true)]
    public $fieldMoney;

    /**
     * @var integer
     */
    #[ORM\Column(name: 'field_duration', type: 'duration', nullable: true)]
    public $fieldDuration;

    /**
     * @var string
     */
    #[ORM\Column(name: 'field_money_value', type: 'money_value', nullable: true)]
    public $fieldMoneyValue;

    /**
     * @var string
     */
    #[ORM\Column(name: 'field_currency', type: 'currency', nullable: true)]
    public $fieldCurrency;
}
