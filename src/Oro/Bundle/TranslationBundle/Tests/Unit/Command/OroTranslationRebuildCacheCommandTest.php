<?php

namespace Oro\Bundle\TranslationBundle\Tests\Unit\Command;

use Oro\Bundle\TranslationBundle\Cache\RebuildTranslationCacheProcessor;
use Oro\Bundle\TranslationBundle\Command\OroTranslationRebuildCacheCommand;
use Oro\Bundle\TranslationBundle\Translation\TranslationMessageSanitizationError;
use Oro\Bundle\TranslationBundle\Translation\TranslationMessageSanitizationErrorCollection;
use Oro\Component\Testing\Command\CommandTestingTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OroTranslationRebuildCacheCommandTest extends TestCase
{
    use CommandTestingTrait;

    private RebuildTranslationCacheProcessor&MockObject $rebuildTranslationCacheProcessor;
    private TranslationMessageSanitizationErrorCollection&MockObject $sanitizationErrorCollection;
    private OroTranslationRebuildCacheCommand $command;

    #[\Override]
    protected function setUp(): void
    {
        $this->rebuildTranslationCacheProcessor = $this->createMock(RebuildTranslationCacheProcessor::class);
        $this->sanitizationErrorCollection = $this->createMock(TranslationMessageSanitizationErrorCollection::class);

        $this->command = new OroTranslationRebuildCacheCommand(
            $this->rebuildTranslationCacheProcessor,
            $this->sanitizationErrorCollection
        );
    }

    public function testExecuteSuccess(): void
    {
        $this->rebuildTranslationCacheProcessor->expects(self::once())
            ->method('rebuildCache')
            ->willReturn(true);

        $this->sanitizationErrorCollection->expects($this->never())
            ->method($this->anything());
        $commandTester = $this->doExecuteCommand($this->command);

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'Rebuilding the translation cache ...');
        $this->assertOutputContains($commandTester, 'The rebuild complete.');
    }

    public function testExecuteSuccessWithSanitizationWarnings(): void
    {
        $this->rebuildTranslationCacheProcessor->expects(self::once())
            ->method('rebuildCache')
            ->willReturn(true);

        $this->sanitizationErrorCollection->expects($this->once())
            ->method('all')
            ->willReturn(
                [
                    new TranslationMessageSanitizationError(
                        'en',
                        'messages',
                        'key1',
                        'message1',
                        'sanitized message 1'
                    ),
                    new TranslationMessageSanitizationError(
                        'en',
                        'messages',
                        'key2',
                        'message2',
                        'sanitized message 2'
                    ),
                ]
            );

        $commandTester = $this->doExecuteCommand($this->command, ['--show-sanitization-errors' => true]);

        $this->assertSuccessReturnCode($commandTester);
        $this->assertOutputContains($commandTester, 'Rebuilding the translation cache ...');
        $this->assertOutputContains($commandTester, 'The rebuild complete.');
        $sanitizationErrors = ' -------- ---------- ------------- ------------------ ---------------------'
            . ' Locale Domain Message Key Original Message Sanitized Message'
            . ' -------- ---------- ------------- ------------------ ---------------------'
            . ' en messages key1 message1 sanitized message 1'
            . ' en messages key2 message2 sanitized message 2'
            . ' -------- ---------- ------------- ------------------ ---------------------';

        $this->assertOutputContains($commandTester, $sanitizationErrors);
    }

    public function testExecuteFailed(): void
    {
        $this->rebuildTranslationCacheProcessor->expects(self::once())
            ->method('rebuildCache')
            ->willReturn(false);

        $commandTester = $this->doExecuteCommand($this->command);

        $this->assertOutputContains($commandTester, 'Rebuilding the translation cache ...');
        $this->assertProducedError($commandTester, 'The rebuild failed.');
    }
}
