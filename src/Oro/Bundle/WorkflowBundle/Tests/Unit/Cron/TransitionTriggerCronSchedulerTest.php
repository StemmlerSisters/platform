<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Cron;

use Oro\Bundle\CronBundle\Entity\Manager\DeferredScheduler;
use Oro\Bundle\WorkflowBundle\Cron\TransitionTriggerCronScheduler;
use Oro\Bundle\WorkflowBundle\Entity\TransitionCronTrigger;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransitionTriggerCronSchedulerTest extends TestCase
{
    use EntityTrait;

    private TransitionTriggerCronScheduler $scheduler;
    private DeferredScheduler&MockObject $deferredScheduler;

    #[\Override]
    protected function setUp(): void
    {
        $this->deferredScheduler = $this->createMock(DeferredScheduler::class);

        $this->scheduler = new TransitionTriggerCronScheduler($this->deferredScheduler);
    }

    public function testAddSchedule(): void
    {
        $cronTrigger = $this->createTrigger(['cron' => '* * * * *', 'id' => 42]);

        $this->deferredScheduler->expects($this->once())
            ->method('addSchedule')
            ->with(
                'oro:workflow:handle-transition-cron-trigger',
                $this->callback(function ($argumentsCallback) {
                    if (!is_callable($argumentsCallback)) {
                        return false;
                    }

                    return ['--id=42'] === call_user_func($argumentsCallback);
                }),
                '* * * * *'
            );

        $this->scheduler->addSchedule($cronTrigger);
    }

    /**
     * @param array $properties
     * @return TransitionCronTrigger
     */
    private function createTrigger(array $properties)
    {
        $trigger = new TransitionCronTrigger();
        foreach ($properties as $propertyName => $propertyValue) {
            $this->setValue($trigger, $propertyName, $propertyValue);
        }

        return $trigger;
    }

    public function testRemoveSchedule(): void
    {
        $cronTrigger = $this->createTrigger(['cron' => '* * * * *', 'id' => 42]);

        $this->deferredScheduler->expects($this->once())
            ->method('removeSchedule')
            ->with(
                'oro:workflow:handle-transition-cron-trigger',
                ['--id=42'],
                '* * * * *'
            );
        $this->scheduler->removeSchedule($cronTrigger);
    }

    public function testFlush(): void
    {
        $this->deferredScheduler->expects($this->once())
            ->method('flush');
        $this->scheduler->flush();
    }
}
