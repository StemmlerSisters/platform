<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Validator\Constraints;

use Oro\Bundle\EntityExtendBundle\Tools\ExtendDbIdentifierNameGenerator;
use Oro\Bundle\EntityExtendBundle\Validator\Constraints\FieldNameLength;
use Oro\Bundle\EntityExtendBundle\Validator\Constraints\FieldNameLengthValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class FieldNameLengthValidatorTest extends ConstraintValidatorTestCase
{
    private const STRING = 'FieldNameFieldNameFieldNameFieldNameFieldName';

    private ExtendDbIdentifierNameGenerator&MockObject $nameGenerator;

    #[\Override]
    protected function setUp(): void
    {
        $this->nameGenerator = $this->createMock(ExtendDbIdentifierNameGenerator::class);
        parent::setUp();
    }

    #[\Override]
    protected function createValidator(): FieldNameLengthValidator
    {
        return new FieldNameLengthValidator($this->nameGenerator);
    }

    public function testValidateException()
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage(
            sprintf('Expected argument of type "%s", "%s" given', FieldNameLength::class, Length::class)
        );

        $this->validator->validate(self::STRING, new Length(['min' => 1]));
    }

    public function testValidateWhenMaxLengthExceeded()
    {
        $maxLength = 22;
        $length = 23;
        $value = substr(self::STRING, 0, $length);

        $this->nameGenerator->expects($this->once())
            ->method('getMaxCustomEntityFieldNameSize')
            ->willReturn($maxLength);

        $constraint = new FieldNameLength();
        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->maxMessage)
            ->setParameter('{{ value }}', '"' . $value . '"')
            ->setParameter('{{ limit }}', $maxLength)
            ->setParameter('{{ value_length }}', $length)
            ->setInvalidValue($value)
            ->setPlural($maxLength)
            ->setCode(FieldNameLength::TOO_LONG_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider maxLengthNotExceededDataProvider
     */
    public function testValidateMaxLengthNotExceeded(string $value)
    {
        $maxLength = 22;

        $this->nameGenerator->expects($this->once())
            ->method('getMaxCustomEntityFieldNameSize')
            ->willReturn($maxLength);

        $constraint = new FieldNameLength();
        $this->validator->validate($value, $constraint);
        $this->assertNoViolation();
    }

    public function maxLengthNotExceededDataProvider(): array
    {
        return [
            [substr(self::STRING, 0, 21)],
            [substr(self::STRING, 0, 22)],
        ];
    }

    public function testValidateMinLengthExceeded()
    {
        $minLength = 2;
        $length = 1;
        $value = 'A';

        $constraint = new FieldNameLength();
        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->minMessage)
            ->setParameter('{{ value }}', '"' . $value . '"')
            ->setParameter('{{ limit }}', $minLength)
            ->setParameter('{{ value_length }}', $length)
            ->setInvalidValue($value)
            ->setPlural($minLength)
            ->setCode(FieldNameLength::TOO_SHORT_ERROR)
            ->assertRaised();
    }

    public function testValidateMinLengthNotExceeded()
    {
        $value = 'AA';

        $constraint = new FieldNameLength();
        $this->validator->validate($value, $constraint);
        $this->assertNoViolation();
    }
}
