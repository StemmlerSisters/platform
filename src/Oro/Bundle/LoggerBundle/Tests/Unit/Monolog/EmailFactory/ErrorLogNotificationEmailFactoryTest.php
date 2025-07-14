<?php

namespace Oro\Bundle\LoggerBundle\Tests\Unit\Monolog\EmailFactory;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\LoggerBundle\Monolog\EmailFactory\ErrorLogNotificationEmailFactory;
use Oro\Bundle\LoggerBundle\Provider\ErrorLogNotificationRecipientsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email as SymfonyEmail;

class ErrorLogNotificationEmailFactoryTest extends TestCase
{
    private ConfigManager&MockObject $configManager;
    private ErrorLogNotificationRecipientsProvider&MockObject $recipientsProvider;
    private ErrorLogNotificationEmailFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->recipientsProvider = $this->createMock(ErrorLogNotificationRecipientsProvider::class);

        $this->factory = new ErrorLogNotificationEmailFactory($this->configManager, $this->recipientsProvider);
    }

    /**
     * @dataProvider getCreateEmailDataProvider
     */
    public function testCreateEmail(array $recipients): void
    {
        $sender = 'sender@example.com';
        $expectedEmail = (new SymfonyEmail())
            ->subject('Sample subject')
            ->to(...$recipients)
            ->from($sender);

        $this->recipientsProvider->expects(self::once())
            ->method('getRecipientsEmailAddresses')
            ->willReturn($recipients);

        $this->configManager->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['oro_notification.email_notification_sender_email', false, false, null, 'sender@example.com'],
                ['oro_logger.email_notification_subject', false, false, null, 'Sample subject']
            ]);

        $email = $this->factory->createEmail('', []);

        self::assertEquals($expectedEmail->getSubject(), $email->getSubject());
        self::assertEquals($expectedEmail->getFrom(), $email->getFrom());
        self::assertEquals($expectedEmail->getTo(), $email->getTo());
    }

    public function getCreateEmailDataProvider(): array
    {
        return [
            [
                'recipients' => [],
            ],
            [
                'recipients' => ['recipient1@example.com', 'recipient2@example.com'],
            ],
        ];
    }
}
