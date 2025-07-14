<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Handler;

use Oro\Bundle\ImportExportBundle\Async\Topic\ImportTopic;
use Oro\Bundle\ImportExportBundle\File\FileManager;
use Oro\Bundle\ImportExportBundle\Handler\PostponedRowsHandler;
use Oro\Bundle\ImportExportBundle\Writer\WriterChain;
use Oro\Bundle\MessageQueueBundle\Entity\Job;
use Oro\Component\MessageQueue\Client\Message;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Job\Extension\ExtensionInterface;
use Oro\Component\MessageQueue\Job\JobProcessor;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Topic\TopicRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class PostponedRowsHandlerTest extends TestCase
{
    private MessageProducerInterface&MockObject $messageProducer;
    private Job&MockObject $currentJob;
    private JobRunner $jobRunner;
    private JobProcessor&MockObject $jobProcessor;
    private TranslatorInterface&MockObject $translator;
    private TopicRegistry&MockObject $topicRegistry;
    private PostponedRowsHandler $handler;

    #[\Override]
    protected function setUp(): void
    {
        $fileManagerMock = $this->createMock(FileManager::class);
        $writerChain = $this->createMock(WriterChain::class);
        $this->messageProducer = $this->createMock(MessageProducerInterface::class);
        $this->currentJob = $this->createMock(Job::class);
        $rootJob = $this->createMock(Job::class);
        $this->jobProcessor = $this->createMock(JobProcessor::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->topicRegistry = $this->createMock(TopicRegistry::class);

        $this->handler = new PostponedRowsHandler(
            $fileManagerMock,
            $this->messageProducer,
            $writerChain,
            $this->translator
        );

        $rootJob->expects(self::any())
            ->method('getName')
            ->willReturn('name');
        $this->currentJob->expects(self::any())
            ->method('getRootJob')
            ->willReturn($rootJob);
        $this->currentJob->expects(self::any())
            ->method('getId')
            ->willReturn(1);
        $jobExtension = $this->createMock(ExtensionInterface::class);
        $this->jobRunner = new JobRunner($this->jobProcessor, $jobExtension, $this->topicRegistry, $this->currentJob);
    }

    public function testItCreatesIncrementedJob(): void
    {
        $this->jobProcessor->expects(self::any())
            ->method('findOrCreateChildJob')
            ->willReturn($this->currentJob);

        $expectedMessage = new Message();
        $expectedMessage->setBody(
            [
                'jobId' => 1,
                'attempts' => 1,
                'fileName' => '',
                'options' => [
                    'incremented_read' => false,
                    'attempts' => 1,
                    'max_attempts' => PostponedRowsHandler::MAX_ATTEMPTS,
                ],
            ]
        );
        $expectedMessage->setDelay(PostponedRowsHandler::DELAY_SECONDS);
        $this->messageProducer->expects($this->once())
            ->method('send')
            ->with(
                ImportTopic::getName(),
                $expectedMessage
            );

        $result = [];
        $body = ['attempts' => 0];

        $this->handler->postpone($this->jobRunner, $this->currentJob, '', $body, $result);
    }

    public function testItStopsAfterFifthAttempt(): void
    {
        $this->jobProcessor->expects(self::any())
            ->method('findOrCreateChildJob')
            ->willReturn($this->currentJob);

        $result = [];
        $body = ['attempts' => PostponedRowsHandler::MAX_ATTEMPTS];
        $this->messageProducer->expects($this->never())
            ->method('send');
        $this->handler->postpone($this->jobRunner, $this->currentJob, '', $body, $result);
    }

    public function testItAddsErrorMessageWhenPostponeRowsPresent(): void
    {
        $this->jobProcessor->expects(self::any())
            ->method('findOrCreateChildJob')
            ->willReturn($this->currentJob);

        $body = ['attempts' => PostponedRowsHandler::MAX_ATTEMPTS];
        $result = [];
        $result['postponedRows'] = ['elem1', 'elem2'];

        $result['counts']['errors'] = 0;
        $this->messageProducer->expects($this->never())
            ->method('send');
        $this->translator->expects($this->once())
            ->method('trans')
            ->with(
                'oro.importexport.import.postponed_rows',
                ['%postponedRows%' => 2]
            );
        $this->handler->postpone($this->jobRunner, $this->currentJob, '', $body, $result);
    }

    public function testPostponeWithIncrementedReadOption(): void
    {
        $this->jobProcessor->expects(self::any())
            ->method('findOrCreateChildJob')
            ->willReturn($this->currentJob);

        $expectedMessage = new Message();
        $expectedMessage->setBody(
            [
                'jobId' => 1,
                'attempts' => 1,
                'fileName' => '',
                'options' => [
                    'incremented_read' => false,
                    'attempts' => 1,
                    'max_attempts' => PostponedRowsHandler::MAX_ATTEMPTS,
                ],
            ]
        );
        $expectedMessage->setDelay(PostponedRowsHandler::DELAY_SECONDS);
        $this->messageProducer->expects($this->once())
            ->method('send')
            ->with(
                ImportTopic::getName(),
                $expectedMessage
            );

        $result = [];
        $body = ['attempts' => 0, 'options' => []];

        $this->handler->postpone($this->jobRunner, $this->currentJob, '', $body, $result);
    }
}
