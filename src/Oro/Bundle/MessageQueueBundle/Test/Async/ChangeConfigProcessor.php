<?php

namespace Oro\Bundle\MessageQueueBundle\Test\Async;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\MessageQueueBundle\Test\Async\Topic\ChangeConfigTestTopic;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;

/**
 * Test config processor
 */
class ChangeConfigProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    public const COMMAND_NOOP = 'noop';
    public const COMMAND_CHANGE_CACHE = 'change';

    public function __construct(
        private readonly ConfigManager $configManager
    ) {
    }

    #[\Override]
    public function process(MessageInterface $message, SessionInterface $session): string
    {
        usleep(2000000); // Remove after BAP-16453 is fixed
        $messageBody = $message->getBody();
        if ($messageBody['message'] !== self::COMMAND_NOOP) {
            $this->configManager->set('oro_locale.timezone', 'Europe/London');
            $this->configManager->flush();
        }

        return self::ACK;
    }

    #[\Override]
    public static function getSubscribedTopics(): array
    {
        return [ChangeConfigTestTopic::getName()];
    }
}
