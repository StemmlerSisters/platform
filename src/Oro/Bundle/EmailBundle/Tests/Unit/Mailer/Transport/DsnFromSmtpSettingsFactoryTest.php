<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Mailer\Transport;

use Oro\Bundle\EmailBundle\Form\Model\SmtpSettings;
use Oro\Bundle\EmailBundle\Mailer\Transport\DsnFromSmtpSettingsFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Dsn;

class DsnFromSmtpSettingsFactoryTest extends TestCase
{
    private DsnFromSmtpSettingsFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new DsnFromSmtpSettingsFactory();
    }

    /**
     * @dataProvider createReturnsDsnWithSmtpsWhenEncryptionTlsDataProvider
     */
    public function testCreateReturnsDsnWithSmtpsWhenEncryptionTls(?string $smtpEncryption, Dsn $expectedDsn): void
    {
        $userEmailOrigin = (new SmtpSettings())
            ->setEncryption($smtpEncryption)
            ->setHost('sample-host')
            ->setUsername('sample-user')
            ->setPassword('sample-password')
            ->setPort(42);

        self::assertEquals($expectedDsn, $this->factory->create($userEmailOrigin));
    }

    public function createReturnsDsnWithSmtpsWhenEncryptionTlsDataProvider(): array
    {
        return [
            [
                'tls',
                new Dsn('smtp', 'sample-host', 'sample-user', 'sample-password', 42),
            ],
            [
                'ssl',
                new Dsn('smtps', 'sample-host', 'sample-user', 'sample-password', 42),
            ],
            [
                '',
                new Dsn('smtp', 'sample-host', 'sample-user', 'sample-password', 42),
            ],

            [
                null,
                new Dsn('smtp', 'sample-host', 'sample-user', 'sample-password', 42),
            ],
        ];
    }
}
