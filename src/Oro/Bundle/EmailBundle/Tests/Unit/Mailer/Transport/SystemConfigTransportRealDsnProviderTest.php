<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Mailer\Transport;

use Oro\Bundle\EmailBundle\Form\Model\SmtpSettings;
use Oro\Bundle\EmailBundle\Mailer\Transport\DsnFromSmtpSettingsFactory;
use Oro\Bundle\EmailBundle\Mailer\Transport\SystemConfigTransportRealDsnProvider;
use Oro\Bundle\EmailBundle\Provider\SmtpSettingsProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Dsn;

class SystemConfigTransportRealDsnProviderTest extends TestCase
{
    private SmtpSettingsProviderInterface&MockObject $smtpSettingsProvider;
    private DsnFromSmtpSettingsFactory&MockObject $dsnFromSmtpSettingsFactory;
    private SystemConfigTransportRealDsnProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->smtpSettingsProvider = $this->createMock(SmtpSettingsProviderInterface::class);
        $this->dsnFromSmtpSettingsFactory = $this->createMock(DsnFromSmtpSettingsFactory::class);

        $this->provider = new SystemConfigTransportRealDsnProvider(
            $this->smtpSettingsProvider,
            $this->dsnFromSmtpSettingsFactory
        );
    }

    public function testGetRealDsnWhenInvalidArgument(): void
    {
        $this->smtpSettingsProvider->expects(self::never())
            ->method(self::anything());

        $this->expectExceptionObject(new \InvalidArgumentException('Dsn was expected to be "oro://system-config"'));

        $this->provider->getRealDsn(Dsn::fromString('null://null'));
    }

    public function testGetRealDsnWhenSmtpSettingsEligible(): void
    {
        $smtpSettings = (new SmtpSettings())
            ->setHost('example.org')
            ->setPort(465)
            ->setEncryption('ssl');
        $this->smtpSettingsProvider->expects(self::once())
            ->method('getSmtpSettings')
            ->willReturn($smtpSettings);

        $dsn = Dsn::fromString('smtp://example.org:465');
        $this->dsnFromSmtpSettingsFactory->expects(self::once())
            ->method('create')
            ->with($smtpSettings)
            ->willReturn($dsn);

        self::assertSame($dsn, $this->provider->getRealDsn(Dsn::fromString('oro://system-config')));
    }

    public function testGetRealDsnReturnsFallbackWhenSmtpSettingsNotEligible(): void
    {
        $smtpSettings = new SmtpSettings();
        $this->smtpSettingsProvider->expects(self::once())
            ->method('getSmtpSettings')
            ->willReturn($smtpSettings);

        $this->dsnFromSmtpSettingsFactory->expects(self::never())
            ->method(self::anything());

        self::assertEquals(
            Dsn::fromString('smtp://example.org:465'),
            $this->provider->getRealDsn(Dsn::fromString('oro://system-config?fallback=smtp://example.org:465'))
        );
    }

    public function testGetRealDsnReturnsNativeWhenSmtpSettingsNotEligibleAndNoFallback(): void
    {
        $smtpSettings = new SmtpSettings();
        $this->smtpSettingsProvider->expects(self::once())
            ->method('getSmtpSettings')
            ->willReturn($smtpSettings);

        $this->dsnFromSmtpSettingsFactory->expects(self::never())
            ->method(self::anything());

        self::assertEquals(
            Dsn::fromString('native://default'),
            $this->provider->getRealDsn(Dsn::fromString('oro://system-config'))
        );
    }
}
