<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\DoctrineExtension\Dbal\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Oro\Bundle\SecurityBundle\DoctrineExtension\Dbal\Types\CryptedStringType;
use Oro\Bundle\SecurityBundle\Encoder\SymmetricCrypterInterface;
use PHPUnit\Framework\TestCase;

class CryptedStringTypeTest extends TestCase
{
    private CryptedStringType $type;

    #[\Override]
    protected function setUp(): void
    {
        $crypter = $this->createMock(SymmetricCrypterInterface::class);
        $crypter->expects(self::any())
            ->method('encryptData')
            ->willReturnCallback(function ($value) {
                return 'crypted_' . $value;
            });
        $crypter->expects(self::any())
            ->method('decryptData')
            ->willReturnCallback(function ($value) {
                return str_replace('crypted_', '', $value);
            });

        CryptedStringType::setCrypter($crypter);

        $this->type = new CryptedStringType();
    }

    public function testConvertToDatabaseValue(): void
    {
        $testString = 'test';
        $this->assertEquals(
            'crypted_' . $testString,
            $this->type->convertToDatabaseValue($testString, $this->createMock(AbstractPlatform::class))
        );
    }

    public function testConvertToPHPValue(): void
    {
        $testString = 'test';
        $this->assertEquals(
            $testString,
            $this->type->convertToPHPValue('crypted_' . $testString, $this->createMock(AbstractPlatform::class))
        );
    }
}
