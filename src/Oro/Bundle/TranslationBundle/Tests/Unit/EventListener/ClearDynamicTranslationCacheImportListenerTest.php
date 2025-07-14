<?php

namespace Oro\Bundle\TranslationBundle\Tests\Unit\EventListener;

use Oro\Bundle\BatchBundle\Entity\JobExecution;
use Oro\Bundle\BatchBundle\Item\ExecutionContext;
use Oro\Bundle\ImportExportBundle\Event\AfterJobExecutionEvent;
use Oro\Bundle\ImportExportBundle\Job\JobResult;
use Oro\Bundle\TranslationBundle\EventListener\ClearDynamicTranslationCacheImportListener;
use Oro\Bundle\TranslationBundle\Translation\DynamicTranslationCache;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClearDynamicTranslationCacheImportListenerTest extends TestCase
{
    private const JOB_NAME = 'test_job_name';

    private DynamicTranslationCache&MockObject $dynamicTranslationCache;
    private ClearDynamicTranslationCacheImportListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->dynamicTranslationCache = $this->createMock(DynamicTranslationCache::class);

        $this->listener = new ClearDynamicTranslationCacheImportListener(
            $this->dynamicTranslationCache,
            self::JOB_NAME
        );
    }

    public function testOnAfterImportTranslationsJobFailed(): void
    {
        $event = $this->getAfterJobExecutionEvent();

        $this->dynamicTranslationCache->expects($this->never())
            ->method($this->anything());

        $this->listener->onAfterImportTranslations($event);
    }

    public function testOnAfterImportTranslationsJobSuccessfulUnknownJob(): void
    {
        $event = $this->getAfterJobExecutionEvent(true, 'unknown');

        $this->dynamicTranslationCache->expects($this->never())
            ->method($this->anything());

        $this->listener->onAfterImportTranslations($event);
    }

    public function testOnAfterImportTranslationsJobSuccessfulWithoutLanguageCode(): void
    {
        $event = $this->getAfterJobExecutionEvent(true, self::JOB_NAME);

        $this->dynamicTranslationCache->expects($this->never())
            ->method($this->anything());

        $this->listener->onAfterImportTranslations($event);
    }

    public function testOnAfterImportTranslationsJobSuccessfulWithLanguageCode(): void
    {
        $locale = 'en';

        $event = $this->getAfterJobExecutionEvent(true, self::JOB_NAME, $locale);

        $this->dynamicTranslationCache->expects($this->once())
            ->method('delete')
            ->with([$locale]);

        $this->listener->onAfterImportTranslations($event);
    }

    private function getAfterJobExecutionEvent(
        bool $jobIsSuccessful = false,
        string $jobLabel = '',
        string $languageCode = ''
    ): AfterJobExecutionEvent {
        $executionContext = $this->createMock(ExecutionContext::class);
        $executionContext->expects($this->any())
            ->method('get')
            ->with('language_code')
            ->willReturn($languageCode);

        $jobExecution = $this->createMock(JobExecution::class);
        $jobExecution->expects($this->any())
            ->method('getLabel')
            ->willReturn($jobLabel);
        $jobExecution->expects($this->any())
            ->method('getExecutionContext')
            ->willReturn($executionContext);

        $jobResult = $this->createMock(JobResult::class);
        $jobResult->expects($this->once())
            ->method('isSuccessful')
            ->willReturn($jobIsSuccessful);

        return new AfterJobExecutionEvent($jobExecution, $jobResult);
    }
}
